<?php

namespace App\CoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

use App\CoreBundle\Entity\Project;

use Exception;
use DateTime;

class DefaultController extends Controller
{
    public function indexAction()
    {
        if ($this->get('security.context')->isGranted('ROLE_USER')) {
            return $this->dashboardAction();
        }

        return $this->render('AppCoreBundle:Default:index.html.twig');
    }

    public function dashboardAction()
    {
        return $this->render('AppCoreBundle:Default:dashboard.html.twig');
    }

    public function projectsImportAction()
    {
        $existingProjects = [];

        foreach ($this->getUser()->getProjects() as $project) {
            $existingProjects[$project->getName()] = '#';
        }

        return $this->render('AppCoreBundle:Default:projectsImport.html.twig', [
            'access_token' => $this->getUser()->getAccessToken(),
            'existing_projects' => $existingProjects,
        ]);
    }

    public function projectImportAction(Request $request)
    {
        try {
            $project = new Project();
            $project->setOwner($this->getUser());
            $project->setName($request->request->get('name'));
            $project->setGitUrl($request->request->get('git_url'));

            $now = new DateTime();
            $project->setCreatedAt($now);
            $project->setUpdatedAt($now);

            $em = $this->getDoctrine()->getManager();
            $em->persist($project);
            $em->flush();

            return new JsonResponse(['url' => '#', 'project' => ['name' => $project->getName()]], 201);            
        } catch (Exception $e) {
            return new JsonResponse(['message' => $e->getMessage()], 500);
        }
    }
}
