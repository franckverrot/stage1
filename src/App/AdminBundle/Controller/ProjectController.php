<?php

namespace App\AdminBundle\Controller;

class ProjectController extends Controller
{
    public function triggerAction($id, $hash)
    {
        $project = $this->findProject($id);

        $em = $this->get('doctrine')->getManager();
        $rp = $em->getRepository('AppCoreBundle:Build');

        foreach ($rp->findByHash($hash) as $build) {
            $build->setAllowRebuild(true);
            $em->persist($build);
        }

        $em->flush();

        $client = $this->get('app_core.client.github');
        $client->setDefaultOption('headers/Accept', 'application/vnd.github.v3');
        $client->setDefaultOption('headers/Authorization', 'token '.$project->getUsers()->first()->getAccessToken());

        $request = $client->post('/repos/'.$project->getGithubFullName().'/hooks/'.$project->getGithubHookId().'/tests');
        $response = $request->send();

        return $this->redirect($this->generateUrl('app_admin_dashboard'));
    }
}