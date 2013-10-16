<?php

namespace App\CoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller as BaseController;
use Symfony\Component\HttpFoundation\Request;

class WaitingListController extends BaseController
{
    public function indexAction(Request $request)
    {
        return $this->render('AppCoreBundle:WaitingList:index.html.twig', [
            'waiting_list' => $request->getSession()->get('waiting_list', 0),
        ]);
    }
}