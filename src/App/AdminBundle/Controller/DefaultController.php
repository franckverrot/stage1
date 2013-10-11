<?php

namespace App\AdminBundle\Controller;

class DefaultController extends Controller
{
    public function indexAction()
    {
        $doctrine = $this->getDoctrine();

        $projects = $doctrine
            ->getRepository('AppCoreBundle:Project')
            ->findBy([], ['createdAt' => 'DESC'], 10);
            
        $users = $doctrine
            ->getRepository('AppCoreBundle:User')
            ->findBy([], ['createdAt' => 'DESC'], 10);

        $builds = $doctrine
            ->getRepository('AppCoreBundle:Build')
            ->findBy([], ['createdAt' => 'DESC'], 50);

        return $this->render('AppAdminBundle:Default:index.html.twig', [
            'projects' => $projects,
            'users' => $users,
            'builds' => $builds,
        ]);
    }
}