<?php

namespace App\CoreBundle\Controller;

use App\Model\Branch;
use App\Model\Build;
use App\Model\Project;
use App\Model\GithubPayload;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

use Exception;

class DefaultController extends Controller
{
    public function overlayAction()
    {
        $response = $this->render('AppCoreBundle:Default:overlay.html.twig');

        $response->headers->set('Access-Control-Allow-Methods', 'GET POST OPTIONS');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, *');

        return $response;
    }

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

    # @todo move to ProjectController
    public function projectsImportAction()
    {
        $existingProjects = [];

        foreach ($this->getUser()->getProjects() as $project) {
            $existingProjects[$project->getFullName()] = $this->generateUrl('app_core_project_show', ['id' => $project->getId()]);
        }

        return $this->render('AppCoreBundle:Default:projectsImport.html.twig', [
            'access_token' => $this->getUser()->getAccessToken(),
            'existing_projects' => $existingProjects,
            'github_api_base_url' => $this->container->getParameter('github_api_base_url')
        ]);
    }

    # @todo move to ProjectController
    public function projectImportAction(Request $request)
    {
        $this->get('old_sound_rabbit_mq.project_import_producer')->publish(json_encode([
            'request' => $request->request->all(),
            'user_id' => $this->getUser()->getId(),
            'websocket_channel' => $this->getUser()->getChannel(),
            'session_id' => $request->getSession()->getId(),
            'client_ip' => $this->getClientIp(),
        ]));

        return new JsonResponse(json_encode(true));
    }    
}
