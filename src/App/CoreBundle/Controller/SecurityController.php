<?php

namespace App\CoreBundle\Controller;

use App\Model\User;
use App\CoreBundle\SshKeys;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;

use DateTime;
use RuntimeException;

class SecurityController extends Controller
{
    private function isForceEnabled(User $user, SessionInterface $session)
    {
        if ($user->getUsername() === 'ubermuda') {
            return true;
        }

        if (null === ($betaKey = $session->get('beta_key'))) {
            return false;
        }

        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository('Model:BetaSignup');

        return (null !== $repo->findByBetaKey($betaKey));
    }

    /**
     * @todo
     * 
     * Right now this implementation allows anyone to subscribe to any channel
     * just by "asking" for it.
     */
    public function primusAuthAction(Request $request)
    {
        # @todo @channel_auth move channel auth to an authenticator service
        $channel = $request->request->get('channel');
        $token = uniqid(mt_rand(), true);            

        if (strlen($channel) > 0) {
            $repo = $this->getDoctrine()->getRepository('Model:User');
            $authUser = $repo->findByChannel($channel);

            if ($authUser !== $this->getUser()) {
                return new JsonResponse(null, 403);
            }
        } else {
            $channel = $this->getUser()->getChannel();
        }

        $this->get('app_core.redis')->sadd('channel:auth:' . $channel, $token);

        return new JsonResponse(json_encode([
            'channel' => $this->getUser()->getChannel(),
            'token' => $token,
        ]));
    }

    private function registerGithubUser(Request $request, $accessToken)
    {
        $client = $this->container->get('app_core.client.github');
        $client->setDefaultOption('headers/Authorization', 'token '.$accessToken);
        $client->setDefaultOption('headers/Accept', 'application/vnd.github.v3');

        $githubRequest = $client->get('/user');
        $githubResponse = $githubRequest->send();

        $result = $githubResponse->json();

        if (null === ($user = $this->getDoctrine()->getRepository('Model:User')->findOneByGithubId($result['id']))) {
            $user = User::fromGithubResponse($result);
            $user->setStatus(User::STATUS_WAITING_LIST);

            $keys = SshKeys::generate();
            $user->setPublicKey($keys['public']);
            $user->setPrivateKey($keys['private']);
        }

        if (strlen($user->getEmail()) === 0) {
            $githubRequest = $client->get('/user/emails');
            $githubResponse = $githubRequest->send();

            $result = $githubResponse->json();

            foreach ($result as $email) {
                if ($email['primary']) {
                    $user->setEmail($email['email']);
                    break;
                }
            }
        }

        if ($user->getStatus() === User::STATUS_WAITING_LIST) {
            if ($this->isForceEnabled($user, $request->getSession())) {
                $user->setStatus(User::STATUS_ENABLED);
            } else {
                $user->setWaitingList($user->getWaitingList() + 1);
                $this->createBetaSignup($user);
            }
        }

        $user->setLastLoginAt(new DateTime());
        $user->setAccessToken($accessToken);

        $em = $this->getDoctrine()->getManager();
        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function createBetaSignup(User $user)
    {
        $em = $this->get('doctrine')->getManager();
        $repo = $em->getRepository('Model:BetaSignup');

        if (null === $beta = $repo->findOneByEmail($user->getEmail())) {
            $beta = new BetaSignup();
            $beta->setEmail($user->getEmail());
            $beta->setTries($user->getWaitingList());
            $beta->setStatus(BetaSignup::STATUS_DEFAULT);

            $em->persist($beta);            
        }
    }

    public function authorizeAction(Request $request)
    {
        if ($this->container->getParameter('kernel.environment') === 'dev') {
            if (null !== ($user = $this->getDoctrine()->getRepository('Model:User')->findOneByUsername('ubermuda'))) {
                $token = new UsernamePasswordToken($user, null, 'main', $user->getRoles());
                $this->get('security.context')->setToken($token);

                $loginEvent = new InteractiveLoginEvent($request, $token);
                $this->get('event_dispatcher')->dispatch(SecurityEvents::INTERACTIVE_LOGIN, $loginEvent);

                if ($request->getSession()->has('_security.main.target_path')) {
                    $redirectUrl = $request->getSession()->get('_security.main.target_path');
                    $request->getSession()->remove('_security.main.target_path');
                } else {
                    $redirectRoute = (count($user->getProjects()) == 0) ? 'app_core_projects_import' : 'app_core_homepage';
                    $redirectUrl = $this->generateUrl($redirectRoute);
                }

                return $this->redirect($redirectUrl);
            }
        }

        $token = $this->get('form.csrf_provider')->generateCsrfToken('github');
        $this->get('session')->set('csrf_token', $token);

        $payload = [
            'client_id' => $this->container->getParameter('github_client_id'),
            'redirect_uri' => $this->generateUrl('app_core_auth_github_callback', [], true),
            'scope' => 'repo,user:email',
            'state' => $token,
        ];

        return $this->redirect($this->container->getParameter('github_base_url').'/login/oauth/authorize?'.http_build_query($payload));
    }

    public function callbackAction(Request $request)
    {
        $code = $request->query->get('code');
        $token = $request->query->get('state');

        if (!$this->get('form.csrf_provider')->isCsrfTokenValid('github', $token)) {
            throw new RuntimeException('CSRF Mismatch');
        }

        $this->get('session')->remove('csrf_token');

        $payload = [
            'client_id' => $this->container->getParameter('github_client_id'),
            'client_secret' => $this->container->getParameter('github_client_secret'),
            'code' => $code,
        ];

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'content' => http_build_query($payload),
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n".
                            "Accept: application/json\r\n"

            ]
        ]);

        # @todo error management
        $accessTokenUrl = $this->container->getParameter('github_base_url').'/login/oauth/access_token';
        $response = json_decode(file_get_contents($accessTokenUrl, false, $context));

        if (isset($response->error)) {
            $this->addFlash('error', 'An error occured during authentication, please try again later.');
            $this->get('logger')->error('An error occured during authentication', ['error' => $response->error]);
            return $this->redirect($this->generateUrl('app_core_homepage'));
        }

        $user = $this->registerGithubUser($request, $response->access_token);

        if ($user->getStatus() === User::STATUS_WAITING_LIST) {
            $request->getSession()->set('waiting_list', $user->getWaitingList());

            return $this->redirect($this->generateUrl('app_core_waiting_list'));
        }

        $token = new UsernamePasswordToken($user, null, 'main', $user->getRoles());
        $this->get('security.context')->setToken($token);

        $loginEvent = new InteractiveLoginEvent($request, $token);
        $this->get('event_dispatcher')->dispatch(SecurityEvents::INTERACTIVE_LOGIN, $loginEvent);

        if ($request->getSession()->has('_security.main.target_path')) {
            $redirectUrl = $request->getSession()->get('_security.main.target_path');
            $request->getSession()->remove('_security.main.target_path');
        } else {
            $redirectRoute = (count($user->getProjects()) == 0) ? 'app_core_projects_import' : 'app_core_homepage';
            $redirectUrl = $this->generateUrl($redirectRoute);
        }

        return $this->redirect($redirectUrl);
    }

    public function logoutAction()
    {
        $this->get('security.context')->setToken(null);
        $this->get('request')->getSession()->invalidate();

        return $this->redirect($this->generateUrl('app_core_homepage'));
    }
}
