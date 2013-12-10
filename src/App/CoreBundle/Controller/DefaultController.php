<?php

namespace App\CoreBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

use App\CoreBundle\Entity\Project;
use App\CoreBundle\Entity\Build;

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

    public function buildShowAction($id, $forceTab = null)
    {
        $this->setCurrentBuildId($id);

        $build = $this->findBuild($id);
        $this->setCurrentProjectId($build->getProject()->getId());

        if (null === $forceTab) {
            if ($build->isRunning()) {
                $forceTab = 'logs';
            } else {
                $forceTab = 'output';
            }
        }

        return $this->render('AppCoreBundle:Default:buildShow.html.twig', [
            'build' => $build,
            'forceTab' => $forceTab,
        ]);
    }

    public function buildCancelAction($id)
    {
        try {
            $build = $this->findBuild($id);
            $build->setStatus(Build::STATUS_CANCELED);

            $this->persistAndFlush($build);

            $factory = $this->get('app_core.message.factory');
            $message = $factory->createBuildCanceled($build);

            $this->publishWebsocket($message);

            return new JsonResponse(null, 200);
        } catch (Exception $e) {
            return new JsonResponse(['message' => $e->getMessage()], 500);
        }
    }

    public function buildKillAction($id)
    {
        try {
            $build = $this->findBuild($id);
            $build->setStatus(Build::STATUS_KILLED);

            $this->persistAndFlush($build);

            $this->get('old_sound_rabbit_mq.kill_producer')->publish(json_encode(['build_id' => $id]));

            return new JsonResponse(null, 200);
        } catch (Exception $e) {
            return new JsonResponse(['message' => $e->getMessage()], 500);
        }
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

        $qb = $this->getDoctrine()->getRepository('AppCoreBundle:Build')->createQueryBuilder('b');

        $builds = $qb
            ->where($qb->expr()->eq('b.project', ':project'))
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
        $project = $this->findProject($id);

        try {
            $ref = $request->request->get('ref');

            if (null === $hash = $request->request->get('hash')) {
                $hash = $this->getHashFromRef($project, $ref);
            }

            $build = new Build();
            $build->setProject($project);
            $build->setInitiator($this->getUser());
            $build->setStatus(Build::STATUS_SCHEDULED);
            $build->setRef($ref);
            $build->setHash($hash);

            $this->persistAndFlush($build);

            $producer = $this->get('old_sound_rabbit_mq.build_producer');
            $producer->publish(json_encode(['build_id' => $build->getId()]));

            $factory = $this->get('app_core.message.factory');
            $message = $factory->createBuildScheduled($build);

            $this->publishWebsocket($message);

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

    public function hooksGithubAction(Request $request)
    {
        $payload = json_decode($request->getContent());

        $project = $this->getDoctrine()->getRepository('AppCoreBundle:Project')->findOneByGithubId($payload->repository->id);

        if (!$project) {
            throw $this->createNotFoundException('Unknown Github project');
        }

        if ($project->getStatus() === Project::STATUS_HOLD) {
            return new JsonResponse(['class' => 'danger', 'message' => 'Project is on hold']);
        }

        try {
            $ref = substr($payload->ref, 11);
            $hash = $payload->after;

            $build = new Build();
            $build->setProject($project);
            $build->setStatus(Build::STATUS_SCHEDULED);
            $build->setRef($ref);
            $build->setHash($hash);

            $this->persistAndFlush($build);

            $producer = $this->get('old_sound_rabbit_mq.build_producer');
            $producer->publish(json_encode(['build_id' => $build->getId()]));

            $factory = $this->get('app_core.message.factory');
            $message = $factory->createBuildScheduled($build);

            $this->publishWebsocket($message);

            return new JsonResponse([
                'build_url' => $this->generateUrl('app_core_build_show', ['id' => $build->getId()]),
                'build' => $build->asMessage(),
            ], 201);
        } catch (Exception $e) {
            $this->container->get('logger')->error($e->getMessage());
            $this->container->get('logger')->error($e->getResponse()->getBody(true));
            return new JsonResponse(['class' => 'danger', 'message' => $e->getMessage()], 500);
        }
    }
}
