<?php

namespace App\AdminBundle\Controller;

class DefaultController extends Controller
{
    public function indexAction()
    {
        $doctrine = $this->getDoctrine();

        $projects = $doctrine
            ->getRepository('AppCoreBundle:Project')
            ->createQueryBuilder('p')
            ->getQuery()
            ->setMaxResults(10)
            ->execute();
            
        $users = $doctrine
            ->getRepository('AppCoreBundle:User')
            ->createQueryBuilder('u')
            ->getQuery()
            ->setMaxResults(10)
            ->execute();

        $builds = $doctrine
            ->getRepository('AppCoreBundle:Build')
            ->createQueryBuilder('b')
            ->getQuery()
            ->setMaxResults(50)
            ->execute();

        return $this->render('AppAdminBundle:Default:index.html.twig', [
            'projects' => $projects,
            'users' => $users,
            'builds' => $builds,
        ]);
    }
}