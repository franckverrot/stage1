<?php

namespace App\CoreBundle\Controller;

use App\CoreBundle\Entity\Feedback;
use Symfony\Component\HttpFoundation\Request;

class FeedbackController extends Controller
{
    public function createAction(Request $request)
    {
        $data = $request->get('feedback');

        $feedback = new Feedback();
        $feedback->setMessage($data['message']);
        $feedback->setUser($this->getUser());

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

        return $this->redirect($data['return']);
    }
}