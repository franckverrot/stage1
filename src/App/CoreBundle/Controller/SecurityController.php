<?php

namespace App\CoreBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;

use App\CoreBundle\Entity\User;

use DateTime;

use RuntimeException;

class SecurityController extends Controller
{
    public function primusAuthAction(Request $request)
    {
        # @todo @channel_auth move channel auth to an authenticator service
        $channel = $request->request->get('channel');

        $token = uniqid(mt_rand(), true);

        $this->get('app_core.redis')->sadd('channel:auth:' . $channel, $token);

        return new JsonResponse(json_encode(['token' => $token]));
    }

    private function registerGithubUser(Request $request, $accessToken)
    {
        $githubApiBaseUrl = $this->container->getParameter('github_api_base_url');

        $result = $this->github_get($githubApiBaseUrl.'/user', $accessToken);

        $now = new DateTime();

        if (null === ($user = $this->getDoctrine()->getRepository('AppCoreBundle:User')->findOneByGithubId($result->id))) {
            $user = User::fromGithubResponse($result);
            $user->setCreatedAt($now);
            $user->setUpdatedAt($now);
            $user->setStatus(User::STATUS_WAITING_LIST);
        }

        # always check if we have a new primary email
        $result = $this->github_get($githubApiBaseUrl.'/user/emails', $accessToken);

        foreach ($result as $email) {
            if ($email->primary && $email->verified) {
                $user->setEmail($email->email);
                break;
            }
        }

        $user->setLastLoginAt($now);
        $user->setAccessToken($accessToken);

        $em = $this->getDoctrine()->getManager();
        $em->persist($user);
        $em->flush();

        $token = new UsernamePasswordToken($user, null, 'main', $user->getRoles());
        $this->get('security.context')->setToken($token);

        $loginEvent = new InteractiveLoginEvent($request, $token);
        $this->get('event_dispatcher')->dispatch(SecurityEvents::INTERACTIVE_LOGIN, $loginEvent);

        return $user;
    }

    public function authorizeAction()
    {
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
        $response = json_decode(file_get_contents($this->container->getParameter('github_base_url').'/login/oauth/access_token', false, $context));

        $user = $this->registerGithubUser($request, $response->access_token);

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
