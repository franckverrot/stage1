<?php

namespace App\CoreBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

use App\CoreBundle\Entity\Project;
use App\CoreBundle\Entity\Build;

use App\CoreBundle\SshKeys;
use App\CoreBundle\Value\ProjectAccess;

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

    public function buildShowAction($id)
    {
        $build = $this->findBuild($id);
        $this->setCurrentProjectId($build->getProject()->getId());

        return $this->render('AppCoreBundle:Default:buildShow.html.twig', [
            'build' => $build,
        ]);
    }

    public function buildCancelAction($id)
    {
        try {
            $build = $this->findBuild($id);
            $project = $build->getProject();
            $build->setStatus(Build::STATUS_CANCELED);

            $this->persistAndFlush($build);

            $buildData = $build->asWebsocketMessage();

            $buildData['schedule_url'] = $this->generateUrl('app_core_project_schedule_build', ['id' => $project->getId()]);

            $this->publishWebsocket('build.canceled', $project->getChannel(), [
                'build' => $buildData,
                'project' => $project->asWebsocketMessage()
            ]);

            return new JsonResponse(null, 200);
        } catch (Exception $e) {
            return new JsonResponse(['message' => $e->getMessage()], 500);
        }
    }

    public function buildKillAction($id)
    {
        try {
            $this->get('old_sound_rabbit_mq.kill_producer')->publish(json_encode(['build_id' => $id]));

            return new JsonResponse(null, 200);
        } catch (Exception $e) {
            return new JsonResponse(['message' => $e->getMessage()], 500);
        }
    }

    public function projectAdminAction($id)
    {
        $project = $this->findProject($id);
        $this->setCurrentProjectId($id);

        # @todo replace with SessionCsrfProvider
        $token = uniqid(mt_rand(), true);
        $this->get('session')->set('csrf_token', $token);

        return $this->render('AppCoreBundle:Default:projectAdmin.html.twig', [
            'project' => $project,
            'csrf_token' => $token,
        ]);
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
        return $this->redirect($this->generateUrl('app_core_project_builds', ['id' => $id]));
    }

    public function projectBuildsAction($id)
    {
        $this->setCurrentProjectId($id);

        $project = $this->findProject($id);

        $qb = $this->getDoctrine()->getRepository('AppCoreBundle:Build')->createQueryBuilder('b');

        $builds = $qb
            ->where($qb->expr()->eq('b.project', ':project'))
            ->orderBy('b.createdAt', 'DESC')
            ->setParameter(':project', $project->getId())
            ->getQuery()
            ->execute();

        $running_builds = array_filter($builds, function($build) { return $build->isRunning(); });
        $pending_builds = array_filter($builds, function($build) { return $build->isPending(); });
        $other_builds = array_filter($builds, function($build) { return !($build->isRunning() || $build->isPending()); });

        return $this->render('AppCoreBundle:Default:projectBuilds.html.twig', [
            'project' => $project,
            'other_builds' => $other_builds,
            'running_builds' => $running_builds,
            'pending_builds' => $pending_builds,
            'has_builds' => count($builds) > 0
        ]);
    }

    public function projectScheduleBuildAction(Request $request, $id)
    {
        $project = $this->findProject($id);

        try {
            $ref = $request->request->get('ref');

            if (null === $hash = $request->request->get('hash')) {
                $url = vsprintf('%s/repos/%s/%s/git/refs/heads', [
                    $this->container->getParameter('github_api_base_url'),
                    $project->getGithubOwnerLogin(),
                    $project->getName(),
                ]);


                $refs = $this->github_get($url);

                $branches = array();

                foreach ($refs as $_) {
                    if ('refs/heads/'.$ref === $_->ref) {
                        $hash = $_->object->sha;
                    }
                }
            }

            $build = new Build();
            $build->setProject($project);
            $build->setInitiator($this->getUser());
            $build->setStatus(Build::STATUS_SCHEDULED);
            $build->setRef($ref);
            $build->setHash($hash);

            $now = new DateTime();
            $build->setCreatedAt($now);
            $build->setUpdatedAt($now);

            $this->persistAndFlush($build);

            $producer = $this->get('old_sound_rabbit_mq.build_producer');
            $producer->publish(json_encode(['build_id' => $build->getId()]));

            $this->publishWebsocket('build.scheduled', $project->getChannel(), [
                'build' => array_replace([
                    'show_url' => $this->generateUrl('app_core_build_show', ['id' => $build->getId()]),
                    'cancel_url' => $this->generateUrl('app_core_build_cancel', ['id' => $build->getId()]),
                ], $build->asWebsocketMessage()),
                'project' => $project->asWebsocketMessage(),
            ]);

            return new JsonResponse([
                'build_url' => $this->generateUrl('app_core_build_show', ['id' => $build->getId()]),
                'cancel_url' => $this->generateUrl('app_core_build_cancel', ['id' => $build->getId()]),
                'build' => $build->asWebsocketMessage(),
            ], 201);
        } catch (Exception $e) {
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
            throw $this->createNotFoundException();
        }

        try {
            $ref = substr($payload->ref, 11);
            $hash = $payload->after;

            $build = new Build();
            $build->setProject($project);
            $build->setStatus(Build::STATUS_SCHEDULED);
            $build->setRef($ref);
            $build->setHash($hash);

            $now = new DateTime();
            $build->setCreatedAt($now);
            $build->setUpdatedAt($now);

            $this->persistAndFlush($build);

            $producer = $this->get('old_sound_rabbit_mq.build_producer');
            $producer->publish(json_encode(['build_id' => $build->getId()]));

            $this->publishWebsocket('build.scheduled', $project->getChannel(), [
                'build' => array_replace([
                    'show_url' => $this->generateUrl('app_core_build_show', ['id' => $build->getId()]),
                ], $build->asWebsocketMessage()),
                'project' => $project->asWebsocketMessage(),
            ]);

            return new JsonResponse([
                'build_url' => $this->generateUrl('app_core_build_show', ['id' => $build->getId()]),
                'build' => $build->asWebsocketMessage(),
            ], 201);
        } catch (Exception $e) {
            return new JsonResponse(['class' => 'danger', 'message' => $e->getMessage()], 500);
        }
    }
}
