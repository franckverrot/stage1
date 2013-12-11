<?php

namespace App\AdminBundle\Controller;

use Symfony\Component\HttpFoundation\Request;

class BetaController extends Controller
{
    public function indexAction()
    {
        $signups = $this->getDoctrine()->getRepository('AppCoreBundle:BetaSignup')->findBy([], ['createdAt' => 'DESC']);

        return $this->render('AppAdminBundle:Beta:index.html.twig', ['signups' => $signups]);
    }
}