<?php

namespace App\CoreBundle\Controller;

use Symfony\Component\HttpFoundation\Request;

use App\Model\BetaSignup;

class BetaController extends Controller
{
    public function signupAction(Request $request)
    {
        $email = $request->get('email');
        $session = $request->getSession();

        $beta_key = md5(uniqid(mt_rand(), true));
        $session->set('beta_email', $email);

        $beta = $this->getDoctrine()->getRepository('Model:BetaSignup')->findOneByEmail($email);

        if (!$beta) {
            $beta = new BetaSignup();
            $beta->setEmail($email);
            $beta->setBetaKey($beta_key);
        }

        $beta->setTries($beta->getTries() + 1);

        $this->persistAndFlush($beta);

        return $this->redirect($this->generateUrl('app_core_beta_landing'));
    }

    public function landingAction(Request $request)
    {
        $email = $request->getSession()->get('beta_email');
        $beta = $this->getDoctrine()->getRepository('Model:BetaSignup')->findOneByEmail($email);

        if (null === $beta) {
            return $this->render('AppCoreBundle:Default:index.html.twig');
        } else {
            return $this->render('AppCoreBundle:Beta:landing.html.twig', [
                'beta' => $beta,
            ]);            
        }
    }

    public function enterAction(Request $request, $betaKey)
    {
        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository('Model:BetaSignup');

        $betaSignup = $repo->findByBetaKey($betaKey);

        if (!$betaSignup) {
            return $this->redirect($this->generateUrl('app_core_homepage'));
        }

        $request->getSession()->set('beta_key', $betaKey);

        return $this->render('AppCoreBundle:Beta:enter.html.twig', [
            'beta_signup' => $betaSignup,
        ]);
    }
}