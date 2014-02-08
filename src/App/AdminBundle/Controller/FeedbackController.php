<?php

namespace App\AdminBundle\Controller;

class FeedbackController extends Controller
{
    public function indexAction()
    {
        $rp = $this->get('doctrine')->getRepository('AppCoreBundle:Feedback');

        return $this->render('AppAdminBundle:Feedback:index.html.twig', [
            'entries' => $rp->findAll(),
        ]);
    }

    public function showAction($id)
    {
        $rp = $this->get('doctrine')->getRepository('AppCoreBundle:Feedback');

        $entry = $rp->find($id);

        if (!$entry) {
            throw $this->createNotFoundException();
        }

        return $this->render('AppAdminBundle:Feedback:show.html.twig', [
            'entry' => $entry,
        ]);
    }
}