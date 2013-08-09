<?php

namespace App\CoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Process\ProcessBuilder;

use App\CoreBundle\Entity\Project;
use App\CoreBundle\Entity\Build;

use App\CoreBundle\SshKeys;

use RuntimeException;
use Exception;
use DateTime;

class DefaultController extends Controller
{
    private function github_post($url, $payload)
    {
        file_put_contents('/tmp/payload.json', json_encode($payload));
        return json_decode(file_get_contents($url, false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'content' => json_encode($payload),
                'header' => 'Authorization: token '.$this->getUser()->getAccessToken()."\r\n".
                            "Content-Type: application/json\r\n"
            ],
        ])));
    }

    private function github_get($url)
    {
        return json_decode(file_get_contents($url, false, stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'Authorization: token '.$this->getUser()->getAccessToken()."\r\n"
            ],
        ])));
    }

    private function persistAndFlush($entity)
    {
        $em = $this->getDoctrine()->getManager();
        $em->persist($entity);
        $em->flush();
    }

    private function publishWebsocket($event, $data)
    {
        $this->get('old_sound_rabbit_mq.websocket_producer')->publish(json_encode([
            'event' => $event,
            'timestamp' => microtime(true),
            'data' => $data,
        ]));
    }

    private function removeAndFlush($entity)
    {
        $em = $this->getDoctrine()->getManager();
        $em->remove($entity);
        $em->flush();
    }

    private function setCurrentProjectId($id)
    {
        $this->get('request')->attributes->set('current_project_id', $id);
    }

    private function findBuild($id)
    {
        $build = $this->getDoctrine()->getRepository('AppCoreBundle:Build')->find($id);

        if (!$build) {
            throw $this->createNotFoundException();
        }

        if ($build->getProject()->getOwner() != $this->getUser()) {
            throw new AccessDeniedException();
        }

        return $build;
    }

    private function findProject($id)
    {
        $project = $this->getDoctrine()->getRepository('AppCoreBundle:Project')->find($id);

        if (!$project) {
            throw $this->createNotFoundException();
        }

        if ($project->getOwner() != $this->getUser()) {
            throw new AccessDeniedException();
        }

        return $project;
    }

    private function findPendingBuilds($project)
    {
        $qb = $this->getDoctrine()->getRepository('AppCoreBundle:Build')->createQueryBuilder('b');

        $qb
            ->where($qb->expr()->eq('b.project', ':project'))
            ->andWhere($qb->expr()->in('b.status', [Build::STATUS_SCHEDULED, Build::STATUS_BUILDING]))
            ->setParameter(':project', $project->getId());

        return $qb->getQuery()->execute();
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

            $this->publishWebsocket('build.canceled', [
                'build' => $build->asWebsocketMessage(),
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

        $token = md5(uniqid());
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

    public function projectBranchesAction($id)
    {
        $this->setCurrentProjectId($id);

        $project = $this->findProject($id);

        $pendingBuilds = [];

        foreach ($this->findPendingBuilds($project) as $build) {
            $pendingBuilds[$build->getRef()] = [
                'status' => $build->getStatus(),
                'status_label' => $build->getStatusLabel(),
                'status_label_class' => $build->getStatusLabelClass(),
            ];
        }

        return $this->render('AppCoreBundle:Default:projectBranches.html.twig', [
            'project' => $project,
            'pending_builds' => $pendingBuilds,
        ]);        
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
                $builder = new ProcessBuilder([
                    '/usr/bin/git', 'ls-remote', '--heads', $project->getCloneUrl(), $ref
                ]);
                $process = $builder->getProcess();
                $process->run();

                $output = trim($process->getOutput());
                list($hash, $ref) = explode("\t", $output);
                $ref = substr($ref, 11);
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

            $this->publishWebsocket('build.scheduled', [
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

    public function projectDetectBranchesAction($id)
    {
        $project = $this->findProject($id);

        $builder = new ProcessBuilder(['/usr/bin/git', 'ls-remote', '--heads', $project->getCloneUrl()]);
        $process = $builder->getProcess();
        $process->run();

        $output = trim($process->getOutput());
        $lines = explode(PHP_EOL, $output);

        $branches = [];

        foreach ($lines as $line) {
            list($hash, $ref) = explode("\t", $line);

            $branches[] = ['ref' => substr($ref, 11), 'hash' => $hash];
        }

        return new JsonResponse($branches);
    }

    public function projectsImportAction()
    {
        $existingProjects = [];

        foreach ($this->getUser()->getProjects() as $project) {
            $existingProjects[$project->getName()] = $this->generateUrl('app_core_project_show', ['id' => $project->getId()]);
        }

        return $this->render('AppCoreBundle:Default:projectsImport.html.twig', [
            'access_token' => $this->getUser()->getAccessToken(),
            'existing_projects' => $existingProjects,
            'github_api_base_url' => $this->container->getParameter('github_api_base_url')
        ]);
    }

    public function projectImportAction(Request $request)
    {
        try {
            $project = new Project();
            $project->setGithubId($request->request->get('github_id'));
            $project->setGithubOwnerLogin($request->request->get('github_owner_login'));
            $project->setGithubFullName($request->request->get('github_full_name'));
            $project->setOwner($this->getUser());
            $project->setName($request->request->get('name'));
            $project->setCloneUrl($request->request->get('clone_url'));

            $keys = SshKeys::generate();

            $project->setPublicKey($keys['public']);
            $project->setPrivateKey($keys['private']);

            $now = new DateTime();
            $project->setCreatedAt($now);
            $project->setUpdatedAt($now);

            $hooksUrl = $request->request->get('hooks_url');
            $githubHookUrl = $this->generateUrl('app_core_hooks_github', [], true);

            $hooks = $this->github_get($hooksUrl);

            foreach ($hooks as $_) {
                if ($_->name === 'web' && $_->config->url === $githubHookUrl) {
                    $hook = $_;
                    break;
                }
            }

            if (!isset($hook)) {
                $hook = $this->github_post($hooksUrl, [
                    'name' => 'web',
                    'active' => true,
                    'events' => ['push'],
                    'config' => ['url' => $githubHookUrl, 'content_type' => 'json'],
                ]);                
            }

            $project->setGithubHookId($hook->id);

            $keysUrl = str_replace('{/key_id}', '', $request->request->get('keys_url'));
            $keys = $this->github_get($keysUrl);

            $deployKey = $project->getPublicKey();
            $deployKey = substr($deployKey, 0, strrpos($deployKey, ' '));

            foreach ($keys as $_) {
                if ($_->key === $deployKey) {
                    $key = $_;
                    break;
                }
            }

            if (!isset($key)) {
                $key = $this->github_post($keysUrl, [
                    'key' => $deployKey,
                    'title' => 'stage1 deploy key',
                ]);
            }

            $project->setGithubDeployKeyId($key->id);

            $this->persistAndFlush($project);

            return new JsonResponse(['url' => $this->generateUrl('app_core_project_show', ['id' => $project->getId()]), 'project' => ['name' => $project->getName()]], 201);            
        } catch (Exception $e) {
            return new JsonResponse(['message' => $e->getMessage()], 500);
        }
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

            $this->publishWebsocket('build.scheduled', [
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
