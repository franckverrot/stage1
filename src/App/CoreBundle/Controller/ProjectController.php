<?php

namespace App\CoreBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

use App\CoreBundle\Value\ProjectAccess;
use App\CoreBundle\Entity\Project;

class ProjectController extends Controller
{
    private function getAccessForm(Project $project)
    {
        return $this->createForm('project_access', new ProjectAccess(), [
            'action' => $this->generateUrl('app_core_project_access_create', ['id' => $project->getId()]),
            'method' => 'POST',
        ]);
    }

    private function getMasterPasswordForm(Project $project)
    {
        return $this->createForm('project_master_password', $project, [
            'action' => $this->generateUrl('app_core_project_master_password_update', ['id' => $project->getId()]),
            'method' => 'POST',
        ]);
    }

    private function getProjectBaseImageForm(Project $project)
    {
        return $this->createForm('project_base_image', $project, [
            'action' => $this->generateUrl('app_core_project_admin_base_image_update', ['id' => $project->getId()]),
            'method' => 'POST',
        ]);
    }

    /**
     * @param integer $id
     */
    public function adminAction($id)
    {
        $project = $this->findProject($id);
        $this->setCurrentProjectId($id);

        # @todo replace with SessionCsrfProvider
        $token = uniqid(mt_rand(), true);
        $this->get('session')->set('csrf_token', $token);

        return $this->render('AppCoreBundle:Project:admin.html.twig', [
            'base_image_form' => $this->getProjectBaseImageForm($project)->createView(),
            'project' => $project,
            'csrf_token' => $token,
        ]);
    }

    public function updateBaseImageAction(Request $request, $id)
    {
        $project = $this->findProject($id);
        $this->setCurrentProjectId($id);

        $form = $this->getProjectBaseImageForm($project);

        $form->bind($request);

        if ($form->isValid()) {
            $this->persistAndFlush($project);
            return $this->redirect($this->generateUrl('app_core_project_admin', ['id' => $project->getId()]));
        }

        $token = uniqid(mt_rand(), true);
        $this->get('session')->set('csrf_token', $token);

        return $this->render('AppCoreBundle:Project:admin.html.twig', [
            'base_image_form' => $form->createView(),
            'project' => $project,
            'csrf_token' => $token,
        ]);
    }

    /**
     * @param Symfony\Component\HttpFoundation\Request $request
     * @param integer $id
     */
    public function updateEnvAction(Request $request, $id)
    {
        $project = $this->findProject($id);
        $this->setCurrentProjectId($id);

        $project->setEnv($request->request->get('project_env'));

        $this->persistAndFlush($project);

        $this->addFlash('success', 'Project environment saved');

        return $this->redirect($this->generateUrl('app_core_project_admin', ['id' => $project->getId()]));
    }

    public function branchesAction($id)
    {
        $this->setCurrentProjectId($id);
        $project = $this->findProject($id);

        $builds = $this
            ->getDoctrine()
            ->getRepository('AppCoreBundle:Build')
            ->findLastByRefs($project);

        foreach ($builds as $build) {     
            foreach ($project->getActiveBranches() as $branch) {
                if ($branch->getName() == $build->getRef()) {
                    $branch->setLastBuild($build);
                }
            }
        }

        return $this->render('AppCoreBundle:Project:branches.html.twig', [
            'project' => $project,
        ]);
    }

    public function discoverAction()
    {
        $discover = $this->container->get('app_core.discover.github');
        $projects = $discover->discover($this->getUser());

        if (count($projects) === 0) {
            return new JsonResponse(json_encode([]));
        }

        $githubIds = array_values(array_map(function($p) { return $p['github_id']; }, $projects));

        $queryBuilder = $this->getDoctrine()->getRepository('AppCoreBundle:Project')->createQueryBuilder('p');
        $queryBuilder->where('p.githubId IN(?1)');
        $queryBuilder->setParameter(1, $githubIds);

        $query = $queryBuilder->getQuery();

        foreach ($query->execute() as $project) {
            if (isset($projects[$project->getFullName()])) {
                $projects[$project->getFullName()] = array_replace($projects[$project->getFullName()], [
                    'exists' => true,
                    'url' => $this->generateUrl('app_core_project_show', ['id' => $project->getId()]),
                    'users' => $project->getUsers()->map(function($user) { return $user->getUsername(); })->toArray(),
                    'is_in' => $project->getUsers()->contains($this->getUser()),
                    'join_url' => $this->generateUrl('app_core_project_join', ['id' => $project->getId()]),
                ]);
            }
        }

        return new JsonResponse(json_encode($projects));
    }

    public function joinAction(Request $request, $id)
    {
        $project = $this->getDoctrine()->getRepository('AppCoreBundle:Project')->find($id);

        if (!$project) {
            throw $this->createNotFoundException();
        }

        $client = $this->get('app_core.client.github');
        $client->setDefaultOption('headers/Authorization', 'token '.$this->getUser()->getAccessToken());

        $request = $client->get(['/repos/{owner}/{repo}/collaborators', ['owner' => $project->getGithubOwnerLogin(), 'repo' => $project->getName()]]);
        $response = $request->send();
        $users = $response->json();

        foreach ($users as $user) {
            if ($user['id'] === $this->getUser()->getGithubId()) {
                $project->addUser($this->getUser());
                $this->persistAndFlush($project);

                return new JsonResponse(json_encode([
                    'status' => 'ok',
                    'project_url' => $this->generateUrl('app_core_project_show', ['id' => $project->getId()]),
                    'project_full_name' => $project->getFullName(),
                ]));
            }
        }

        return new JsonResponse(json_encode(['status' => 'nok', 'message' => 'Not authorized']), 401);
    }

    public function accessDeleteAction(Request $request, $id)
    {
        $this->setCurrentProjectId($id);
        $project = $this->findProject($id);

        $data = $request->request->all();
        $flashBag = $request->getSession()->getFlashBag();

        if (!$this->get('form.csrf_provider')->isCsrfTokenValid('access_delete', $data['access_delete']['csrf_token'])) {
            $flashBag->add('error', 'Invalid CSRF token.');
        } else {
            $this->revokeProjectAccess($project, new ProjectAccess($data['access_delete']['delete']));
            $flashBag->add('success', 'Access revoked.');
        }

        return $this->redirect($this->generateUrl('app_core_project_access', ['id' => $project->getId()]));
    }

    public function accessCreateAction(Request $request, $id)
    {
        $this->setCurrentProjectId($id);
        $project = $this->findProject($id);

        $form = $this->getAccessForm($project);
        $form->bind($request);

        if ($form->isValid()) {
            $this->grantProjectAccess($project, $form->getData());
            $request->getSession()->getFlashBag()->add('success', 'Access granted.');

            return $this->redirect($this->generateUrl('app_core_project_access', ['id' => $project->getId()]));
        }

        $accessList = $this->getProjectAccessList($project);

        return $this->render('AppCoreBundle:Project:access.html.twig', [
            'project' => $project,
            'access_list' => $accessList,
            'access_form' => $form->createView(),
            'access_delete_csrf_token' => $this->get('form.csrf_provider')->generateCsrfToken('access_delete'),
            'master_password_form' => $this->getMasterPasswordForm($project)->createView(),
        ]);
    }

    public function masterPasswordUpdateAction(Request $request, $id)
    {
        $this->setCurrentProjectId($id);
        $project = $this->findProject($id);

        $form = $this->getMasterPasswordForm($project);

        $form->bind($request);

        if ($form->isValid()) {
            if ($form->get('delete')->isClicked() || strlen($form->getData()->getMasterPassword()) === 0) {
                $project->setMasterPassword(null);
                $message = 'Master password deleted.';
            } else {
                $project->setMasterPassword(password_hash($project->getMasterPassword(), PASSWORD_BCRYPT));
                $message = 'Master password updated.';
            }

            $this->persistAndFlush($project);
            $request->getSession()->getFlashBag()->add('success', $message);

            return $this->redirect($this->generateUrl('app_core_project_access', ['id' => $project->getId()]));
        }

        $accessList = $this->getProjectAccessList($project);

        return $this->render('AppCoreBundle:Project:access.html.twig', [
            'project' => $project,
            'access_list' => $accessList,
            'access_form' => $this->getAccessForm($project)->createView(),
            'access_delete_csrf_token' => $this->get('form.csrf_provider')->generateCsrfToken('access_delete'),
            'master_password_form' => $form->createView(),
        ]);
    }

    public function accessAction($id)
    {
        $this->setCurrentProjectId($id);

        $project = $this->findProject($id);
        $accessList = $this->getProjectAccessList($project);

        return $this->render('AppCoreBundle:Project:access.html.twig', [
            'project' => $project,
            'access_list' => $accessList,
            'access_form' => $this->getAccessForm($project)->createView(),
            'access_delete_csrf_token' => $this->get('form.csrf_provider')->generateCsrfToken('access_delete'),
            'master_password_form' => $this->getMasterPasswordForm($project)->createView(),
        ]);
    }

    public function authAction(Request $request, $slug)
    {
        $project = $this->findProjectBySlug($slug);

        $access = new ProjectAccess($this->getClientIp(), $request->getSession()->getId());
        $isAllowed = $this->isProjectAccessGranted($project, $access);

        if (!$isAllowed && $project->getUsers()->contains($this->getUser())) {
            $this->container->get('app_core.redis')->sadd('auth:'.$project->getSlug(), $request->getSession()->getId());
            $isAllowed = true;
        }

        if (!$isAllowed && !$project->hasMasterPassword()) {
            # assume owner don't want existence of this project to leak
            throw $this->createNotFoundException();
        }

        if ($isAllowed) {
            $runningBuilds = $project->getRunningBuilds();
        }

        return $this->render('AppCoreBundle:Project:auth.html.twig', [
            'project' => $project,
            'is_allowed' => $isAllowed,
            'running_builds' => $isAllowed ? $runningBuilds : [],
            'return' => $request->query->get('return'),
        ]);
    }

    public function authorizeAction(Request $request, $slug)
    {
        $projects = $this->findProjectsBySlug($slug);
        $password = $request->request->get('password');

        foreach ($projects as $project) {
            if (!password_verify($password, $project->getMasterPassword())) {
                continue;
            }

            $access = new ProjectAccess($this->getClientIp(), $request->getSession()->getId());
            
            $this->grantProjectAccess($project, $access);

            if (strlen($return = $request->request->get('return')) > 0) {
                return $this->redirect('http://' . $return);
            }

            $this->addFlash('success', 'You have been authenticated');

            break;
        }


        return $this->render('AppCoreBundle:Project:auth.html.twig', [
            'project' => $project,
            'return' => $request->request->get('return'),
        ]);
    }
}