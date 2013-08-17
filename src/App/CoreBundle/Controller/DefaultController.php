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

        $url = vsprintf('%s/repos/%s/%s/git/refs/heads', [
            $this->container->getParameter('github_api_base_url'),
            $project->getGithubOwnerLogin(),
            $project->getName(),
        ]);


        $refs = $this->github_get($url);

        $branches = array();

        foreach ($refs as $ref) {
            $branches[] = [
                'ref' => substr($ref->ref, 11),
                'hash' => $ref->object->sha,
            ];
        }

        return new JsonResponse($branches);
    }

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

    public function projectImportAction(Request $request)
    {
        try {
            $project = new Project();
            # @todo @normalize
            $project->setSlug(preg_replace('/[^a-z0-9\-]/', '-', strtolower($request->request->get('github_full_name'))));

            # this is one special ip that cannot be revoked
            # it is used to keep the access list "existing"
            # thus activating auth on the staging areas
            # yes, it's a bit hacky.
            $this->grantProjectAccess($project, new ProjectAccess('0.0.0.0'));

            # this, however, is perfectly legit.
            $this->grantProjectAccess($project, new ProjectAccess($this->getClientIp()));

            $project->setGithubId($request->request->get('github_id'));
            $project->setGithubOwnerLogin($request->request->get('github_owner_login'));
            $project->setGithubFullName($request->request->get('github_full_name'));
            $project->setOwner($this->getUser());
            $project->setName($request->request->get('name'));
            $project->setCloneUrl($request->request->get('clone_url'));
            $project->setSshUrl($request->request->get('ssh_url'));

            $keys = SshKeys::generate();

            $project->setPublicKey($keys['public']);
            $project->setPrivateKey($keys['private']);

            $now = new DateTime();
            $project->setCreatedAt($now);
            $project->setUpdatedAt($now);

            $hooksUrl = $request->request->get('hooks_url');
            $githubHookUrl = $this->generateUrl('app_core_hooks_github', [], true);

            $githubHookUrl = str_replace('http://stage1.io', 'http://stage1:stage1@stage1.io', $githubHookUrl);

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

            return new JsonResponse([
                'url' => $this->generateUrl('app_core_project_show', ['id' => $project->getId()]),
                'project' => [
                    'name' => $project->getName(),
                    'full_name' => $project->getFullName(),
                ]
            ], 201);
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
