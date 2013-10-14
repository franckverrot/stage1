<?php

namespace App\CoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller as BaseController;

class WaitingListController extends BaseController
{
    public function indexAction()
    {
        return $this->render('AppCoreBundle:WaitingList:index.html.twig');
    }
}