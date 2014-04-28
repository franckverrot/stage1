<?php

namespace App\AdminBundle\Controller;

use App\Model\BetaSignup;
use Symfony\Component\HttpFoundation\Request;

use DateTime;
use Swift_Message;

class BetaController extends Controller
{
    public function emailSendAction($id)
    {
        $em = $this->getDoctrine()->getManager();
        $beta = $em->getRepository('Model:BetaSignup')->find($id);

        $mailContent = $this->renderView('AppAdminBundle:Beta:_email.html.twig', [
            'beta' => $beta
        ]);

        $message = Swift_Message::newInstance()
            ->setSubject('Your invitation to Stage1\'s very private beta has arrived.')
            ->setFrom('geoffrey@stage1.io', 'Stage1')
            ->setTo($beta->getEmail())
            ->setBody($mailContent);

        $this->get('mailer')->send($message);
        $beta->setStatus(BetaSignup::STATUS_EMAIL_SENT);
        $beta->setEmailSentAt(new DateTime());

        $em->persist($beta);
        $em->flush();

        $this->getRequest()->getSession()->getFlashBag()->add('success', 'message sent to '.$beta->getEmail());

        return $this->redirect($this->generateUrl('app_admin_beta_signups'));
    }

    public function deleteAction(Request $request, $id)
    {

        $em = $this->get('doctrine')->getManager();
        $beta = $em->getRepository('Model:BetaSignup')->find($id);

        if ($beta) {
            $em->remove($beta);
            $em->flush();
        }

        $this->getRequest()->getSession()->getFlashBag()->add('success', 'beta signup deleted');

        return $this->redirect($this->generateUrl('app_admin_beta_signups'));
    }

    public function indexAction(Request $request)
    {
        $criteria = [];

        if (!$request->get('all')) {
            $criteria['status'] = BetaSignup::STATUS_DEFAULT;
        }

        $orderBy = ['createdAt' => 'DESC'];

        $signups = $this
            ->getDoctrine()
            ->getRepository('Model:BetaSignup')
            ->findBy($criteria, $orderBy);

        return $this->render('AppAdminBundle:Beta:index.html.twig', ['signups' => $signups]);
    }
}