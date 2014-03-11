<?php

namespace App\AdminBundle\Controller;

use App\Model\Build;

class DefaultController extends Controller
{
    public function indexAction()
    {
        $doctrine = $this->getDoctrine();

        $projects = $doctrine
            ->getRepository('Model:Project')
            ->findBy([], ['createdAt' => 'DESC'], 5);
            
        $users = $doctrine
            ->getRepository('Model:User')
            ->findBy([], ['createdAt' => 'DESC'], 5);

        $builds = $doctrine
            ->getRepository('Model:Build')
            ->findBy([
                'status' => [Build::STATUS_SCHEDULED, Build::STATUS_BUILDING, Build::STATUS_RUNNING, Build::STATUS_FAILED, Build::STATUS_TIMEOUT],
            ], [
                'createdAt' => 'DESC'
            ], 20);

        return $this->render('AppAdminBundle:Default:index.html.twig', [
            'projects' => $projects,
            'users' => $users,
            'builds' => $builds,
        ]);
    }

    public function switchUserAction()
    {
        return $this->render('AppAdminBundle:Default:switchUser.html.twig', [
            'users' => $this->get('doctrine')->getRepository('Model:User')->findAll()
        ]);
    }
}