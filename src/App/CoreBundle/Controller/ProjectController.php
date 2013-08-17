<?php

namespace App\CoreBundle\Controller;

class ProjectController extends Controller
{
    public function authAction(Request $request, $slug)
    {
        $project = $this->findProjectBySlug($slug);

        return $this->render('AppCoreBundle:Project:auth.html.twig', [
            'project' => $project,
            'return' => $request->query->get('return'),
        ]);
    }

    public function authorizeAction(Request $request, $slug)
    {
        $project = $this->findProjectBySlug($slug);

        $flashBag = $request->getSession()->getFlashBag();

        if ($request->request->get('password') === 'passroot') {
            # @todo fix this
            if (preg_match('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $_SERVER['HTTP_X_FORWARDED_FOR'], $matches)) {
                $redis = new Redis();
                # @todo fix this too
                $redis->connect('127.0.0.1', 6379);
                $redis->sadd('auth:'.$project->getSlug(), $matches[0]);                

                if (strlen($return = $request->request->get('return')) > 0) {
                    return $this->redirect('http://' . $request->request->get('return'));
                }
    
                $flashBag->add('success', 'You have been authenticated');
            } else {
                $flashBag->add('error', 'Unable to find your IP address');
            }
        }

        return $this->render('AppCoreBundle:Project:auth.html.twig', [
            'project' => $project,
            'return' => $request->request->get('return'),
        ]);
    }
}