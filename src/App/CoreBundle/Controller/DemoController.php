<?php

namespace App\CoreBundle\Controller;

class DemoController extends Controller
{
    public function indexAction()
    {
        return $this->render('AppCoreBundle:Demo:index.html.twig');
    }
}