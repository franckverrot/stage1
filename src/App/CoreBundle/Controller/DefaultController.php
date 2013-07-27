<?php

namespace App\CoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class DefaultController extends Controller
{
    private function github($url)
    {
        return json_decode(file_get_contents($url.'?access_token='.$this->getUser()->getAccessToken()));
    }

    public function indexAction()
    {
        if ($this->get('security.context')->isGranted('ROLE_USER')) {
            return $this->dashboardAction();
        }

        return $this->render('AppCoreBundle:Default:index.html.twig');
    }

    public function dashboardAction()
    {
        return $this->render('AppCoreBundle:Default:dashboard.html.twig');
    }

    public function projectsImportAction()
    {
        return $this->render('AppCoreBundle:Default:projectsImport.html.twig', [
            'access_token' => $this->getUser()->getAccessToken(),
        ]);
    }
}
