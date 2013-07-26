<?php

namespace App\CoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;

use App\CoreBundle\Entity\User;

class DefaultController extends Controller
{
    public function loginAction(Request $request, $accessToken)
    {
        $result = file_get_contents('https://api.github.com/user?access_token='.$accessToken);
        $result = json_decode($result);

        if (null === ($user = $this->getDoctrine()->getRepository('AppCoreBundle:User')->findOneByGithubId($result->id))) {
            $user = User::fromGithubResponse($result);
            $user->setAccessToken($accessToken);

            $em = $this->getDoctrine()->getManager();
            $em->persist($user);
            $em->flush();
        }

        $token = new UsernamePasswordToken($user, null, 'main', ['ROLE_USER']);
        $this->get('security.context')->setToken($token);

        $loginEvent = new InteractiveLoginEvent($request, $token);
        $this->get('event_dispatcher')->dispatch(SecurityEvents::INTERACTIVE_LOGIN, $loginEvent);

        return new JsonResponse(['redirect' => '/']);

    }

    public function indexAction()
    {
        return $this->render('AppCoreBundle:Default:index.html.twig');
    }
}
