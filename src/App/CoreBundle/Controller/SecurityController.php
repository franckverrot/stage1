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

class SecurityController extends Controller
{
    public function loginAction(Request $request, $accessToken)
    {
        $result = file_get_contents('https://api.github.com/user?access_token='.$accessToken);
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

        $redirectRoute = ($user->getLastLoginAt() == $user->getCreatedAt()) ? 'app_core_projects_import' : 'app_core_homepage';

        return new JsonResponse(['redirect_url' => $this->generateUrl($redirectRoute)]);
    }

    public function logoutAction()
    {
        $this->get('security.context')->setToken(null);
        $this->get('request')->getSession()->invalidate();

        return $this->redirect($this->generateUrl('app_core_homepage'));
    }
}
