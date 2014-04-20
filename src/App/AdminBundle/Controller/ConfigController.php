<?php

namespace App\AdminBundle\Controller;

class ConfigController extends Controller
{
    public function indexAction()
    {
        return $this->render('AppAdminBundle:Config:index.html.twig', [
            'parameters' => $this->container->getParameterBag()->all(),
        ]);
    }
}