<?php

namespace App\CoreBundle\Controller;

use App\Model\Branch;
use App\Model\Build;
use App\Model\Project;
use App\Model\GithubPayload;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

use Exception;

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

    public function projectDeleteAction(Request $request, $id)
    {
        $project = $this->findProject($id);

        $session = $this->get('session');
        $flash = $session->getFlashBag();

        if ($request->request->get('name') !== $project->getName()) {
            $flash->add('delete-error', 'Project name validation failed.');
            return $this->redirect($this->generateUrl('app_core_project_admin', ['id' => $id]));
        }

        if ($request->request->get('csrf_token') !== $session->get('csrf_token')) {
            $flash->add('delete-error', 'CSRF validation failed.');
            return $this->redirect($this->generateUrl('app_core_project_admin', ['id' => $id]));
        }

        $this->removeAndFlush($project);

        $flash->add('success', sprintf('Project <strong>%s</strong> has been deleted.', $project->getName()));

        return $this->redirect($this->generateUrl('app_core_homepage'));
    }

    public function projectShowAction($id)
    {
        return $this->redirect($this->generateUrl('app_core_project_branches', ['id' => $id]));
    }

    public function projectBuildsAction($id, $all = false)
    {
        $this->setCurrentProjectId($id);

        $project = $this->findProject($id);

        $qb = $this->getDoctrine()->getRepository('Model:Build')->createQueryBuilder('b');

        $builds = $qb
            ->leftJoin('b.branch', 'br')
            ->where($qb->expr()->eq('b.project', ':project'))
            ->andWhere('br.deleted = 0')
            ->orderBy('b.createdAt', 'DESC')
            ->setParameter(':project', $project->getId())
            ->getQuery()
            ->setMaxResults(100)
            ->execute();

        $running_builds = array_filter($builds, function($build) { return $build->isRunning(); });
        $pending_builds = array_filter($builds, function($build) { return $build->isPending(); });
        $other_builds = array_filter($builds, function($build) { return !($build->isRunning() || $build->isPending()); });

        return $this->render('AppCoreBundle:Default:projectBuilds.html.twig', [
            'project' => $project,
            'other_builds' => $other_builds,
            'running_builds' => $running_builds,
            'pending_builds' => $pending_builds,
            'has_builds' => count($builds) > 0,
            'all' => (bool) $all,
        ]);
    }

    public function projectScheduleBuildAction(Request $request, $id)
    {
        try {
            $project = $this->findProject($id);
            $ref = $request->request->get('ref');
            $hash = $this->getHashFromRef($project, $ref);

            $scheduler = $this->container->get('app_core.build_scheduler');
            $build = $scheduler->schedule($project, $ref, $hash, $this->getUser());

            return new JsonResponse([
                'build_url' => $this->generateUrl('app_core_build_show', ['id' => $build->getId()]),
                'cancel_url' => $this->generateUrl('app_core_build_cancel', ['id' => $build->getId()]),
                'build' => $build->asMessage(),
            ], 201);
        } catch (Exception $e) {
            $this->container->get('logger')->error($e->getMessage());
            $this->container->get('logger')->error($e->getResponse()->getBody(true));

            return new JsonResponse(['class' => 'danger', 'message' => $e->getMessage()], 500);
        }
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
