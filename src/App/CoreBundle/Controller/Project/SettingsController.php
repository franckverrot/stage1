<?php

namespace App\CoreBundle\Controller\Project;

use App\CoreBundle\Controller\Controller;
use App\CoreBundle\Entity\ProjectSettings;
use Symfony\Component\HttpFoundation\Request;

class SettingsController extends Controller
{
    public function policyAction(Request $request, $id)
    {
        $project = $this->findProject($id);
        $this->setCurrentProject($project);

        $settings = $project->getSettings() ?: new ProjectSettings();

        $form = $this->createForm('project_policy', $settings, [
            'action' => $this->generateUrl('app_core_project_settings_policy', ['id' => $project->getId()]),
            'method' => 'POST',
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $settings->setProject($project);

            $em = $this->get('doctrine')->getManager();
            $em->persist($settings);
            $em->flush();

            if ($this->get('session')->has('return')) {
                return $this->redirect($this->get('session')->get('return'));
            }

            return $this->redirect($this->generateUrl('app_core_project_settings_policy', ['id' => $project->getId()]));
        }

        return $this->render('AppCoreBundle:Project/Settings:policy.html.twig', [
            'project' => $project,
            'form' => $form->createView(),
        ]);
    }
}