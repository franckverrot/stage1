<?php

namespace App\AdminBundle\Controller;

use App\CoreBundle\Entity\Build;

class DefaultController extends Controller
{
    public function indexAction()
    {
        $doctrine = $this->getDoctrine();

        $projects = $doctrine
            ->getRepository('AppCoreBundle:Project')
            ->findBy([], ['createdAt' => 'DESC'], 5);
            
        $users = $doctrine
            ->getRepository('AppCoreBundle:User')
            ->findBy([], ['createdAt' => 'DESC'], 5);

        $builds = $doctrine
            ->getRepository('AppCoreBundle:Build')
            ->findBy([
                'status' => [Build::STATUS_BUILDING, Build::STATUS_RUNNING, Build::STATUS_FAILED],
            ], [
                'createdAt' => 'DESC'
            ], 20);

        return $this->render('AppAdminBundle:Default:index.html.twig', [
            'projects' => $projects,
            'users' => $users,
            'builds' => $builds,
        ]);
    }
}