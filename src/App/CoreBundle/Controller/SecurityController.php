<?php

namespace App\CoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
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
    private function registerGithubUser(Request $request, $accessToken)
    {
        $result = file_get_contents($this->container->get('github_api_url').'/user?access_token='.$accessToken);
        $result = json_decode($result);

        $now = new DateTime();

        if (null === ($user = $this->getDoctrine()->getRepository('AppCoreBundle:User')->findOneByGithubId($result->id))) {
            $user = User::fromGithubResponse($result);
            $user->setAccessToken($accessToken);
            $user->setCreatedAt($now);
            $user->setUpdatedAt($now);
        }

        $user->setLastLoginAt($now);

        $em = $this->getDoctrine()->getManager();
        $em->persist($user);
        $em->flush();

        $token = new UsernamePasswordToken($user, null, 'main', ['ROLE_USER']);
        $this->get('security.context')->setToken($token);

        $loginEvent = new InteractiveLoginEvent($request, $token);
        $this->get('event_dispatcher')->dispatch(SecurityEvents::INTERACTIVE_LOGIN, $loginEvent);

        return $user;
    }

    public function authorizeAction()
    {
        $token = md5(uniqid());
        $this->get('session')->set('csrf_token', $token);

        $payload = [
            'client_id' => $this->container->getParameter('github_client_id'),
            'redirect_uri' => $this->generateUrl('app_core_auth_github_callback', [], true),
            'scope' => 'repo',
            'state' => $token,
        ];

        return $this->redirect($this->container->getParameter('github_base_url').'/login/oauth/authorize?'.http_build_query($payload));
    }

    public function callbackAction(Request $request)
    {
        $code = $request->query->get('code');
        $token = $request->query->get('state');

        if ($token !== $this->get('session')->get('csrf_token')) {
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

        $response = json_decode(file_get_contents($this->container->getParameter('github_base_url').'/login/oauth/access_token', false, $context));

        $user = $this->registerGithubUser($request, $response->access_token);
        $redirectRoute = ($user->getLastLoginAt() == $user->getCreatedAt()) ? 'app_core_projects_import' : 'app_core_homepage';

        return $this->redirect($this->generateUrl($redirectRoute));
    }

    public function logoutAction()
    {
        $this->get('security.context')->setToken(null);
        $this->get('request')->getSession()->invalidate();

        return $this->redirect($this->generateUrl('app_core_homepage'));
    }
}
