<?php

namespace App\CoreBundle\Controller\Project;

use App\CoreBundle\Controller\Controller;

class SettingsController extends Controller
{
    public function policyAction($id)
    {
        $project = $this->findProject($id);
        $this->setCurrentProject($project);

        return $this->render('AppCoreBundle:Project/Settings:policy.html.twig', [
            'project' => $project,
        ]);
    }
}