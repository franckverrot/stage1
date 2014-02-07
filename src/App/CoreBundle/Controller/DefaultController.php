<?php

namespace App\CoreBundle\Controller;

use App\CoreBundle\Entity\Branch;
use App\CoreBundle\Entity\Build;
use App\CoreBundle\Entity\Project;
use App\CoreBundle\Entity\GithubPayload;

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

        /**
         * @todo application logs do not work yet, so we just force every request to display the build output
         */
        $forceTab = 'output';

        $streamLogs = false;

        if ($forceTab === 'output') {
            $logsList = $build->getLogsList();
            $logsLength = $this->get('app_core.redis')->llen($logsList);

            if ($logsLength < $this->container->getParameter('build_logs_stream_limit')) {
                $streamLogs = true;
            }
        }

        return $this->render('AppCoreBundle:Default:buildShow.html.twig', [
            'build' => $build,
            'forceTab' => $forceTab,
            'streamLogs' => $streamLogs,
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

    public function hooksGithubAction(Request $request)
    {
        try {
            $payload = json_decode($request->getContent());

            $ref = substr($payload->ref, 11);
            $hash = $payload->after;

            $em = $this->getDoctrine()->getManager();

            $project = $em->getRepository('AppCoreBundle:Project')->findOneByGithubId($payload->repository->id);

            if ($hash === '0000000000000000000000000000000000000000') {
                $branch = $em
                    ->getRepository('AppCoreBundle:Branch')
                    ->findOneByProjectAndName($project, $ref);

                $branch->setDeleted(true);

                $em->persist($branch);
                $em->flush();

                return new JsonResponse(json_encode(null), 200);
            }

            if (!$project) {
                throw $this->createNotFoundException('Unknown Github project');
            }

            if ($project->getStatus() === Project::STATUS_HOLD) {
                return new JsonResponse(['class' => 'danger', 'message' => 'Project is on hold']);
            }

            $existingBuild = $em->getRepository('AppCoreBundle:Build')->findOneByHash($hash);

            if (null !== $existingBuild) {
                $this->get('logger')->warn('build already scheduled for hash', ['existing_build' => $existingBuild->getId(), 'hash' => $hash]);
                return new JsonResponse(['class' => 'danger', 'message' => 'Build already scheduled for hash']);
            }

            $scheduler = $this->get('app_core.build_scheduler');

            $initiator = $em->getRepository('AppCoreBundle:User')->findOneByGithubUsername($payload->pusher->name);

            $build = $scheduler->schedule($project, $ref, $hash);

            $payload = new GithubPayload();
            $payload->setPayload($request->getContent());
            $payload->setBuild($build);

            $em->persist($payload);
            $em->flush();

            return new JsonResponse([
                'build_url' => $this->generateUrl('app_core_build_show', ['id' => $build->getId()]),
                'build' => $build->asMessage(),
            ], 201);
        } catch (Exception $e) {
            $this->container->get('logger')->error($e->getMessage());

            if (method_exists($e, 'getResponse')) {
                $this->container->get('logger')->error($e->getResponse()->getBody(true));
            }

            return new JsonResponse(['class' => 'danger', 'message' => $e->getMessage()], 500);
        }
    }
}
