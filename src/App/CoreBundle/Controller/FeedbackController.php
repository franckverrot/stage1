<?php

namespace App\CoreBundle\Controller;

use App\CoreBundle\Entity\Feedback;
use Symfony\Component\HttpFoundation\Request;

use Swift_Message;

class FeedbackController extends Controller
{
    public function createAction(Request $request)
    {
        $data = $request->get('feedback');

        $feedback = new Feedback();
        $feedback->setMessage($data['message']);
        $feedback->setUser($this->getUser());
        $feedback->setUrl($data['url']);
        $feedback->setToken(md5(uniqid(mt_rand(), true)));

        if ($data['current_project_id']) {
            $feedback->setProject($this->findProject($data['current_project_id']));
        }

        if ($data['current_build_id']) {
            $feedback->setBuild($this->findBuild($data['current_build_id']));
        }

        $em = $this->getDoctrine()->getManager();
        $em->persist($feedback);
        $em->flush();

        $this->addFlash('success', 'Your feedback has been sent, we will reach out to you very soon!');

        $from = [$feedback->getUser()->getEmail() => $feedback->getUser()->getUsername()];

        $message = Swift_Message::newInstance()
            ->setFrom($from)
            ->setReplyTo($from)
            ->setTo('geoffrey@stage1.io')
            ->setSubject('Feedback from Stage1')
            ->setBody($feedback->getMessage());

        $headers = $message->getHeaders();
        $headers->addTextHeader('X-Feedback-Token', $feedback->getToken());

        $this->get('mailer')->send($message);

        return $this->redirect($data['return']);
    }
}