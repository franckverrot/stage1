<?php

namespace App\CoreBundle\Controller;

use Symfony\Component\HttpFoundation\Request;

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

    private function getAccessDeleteForm(Project $project, $accessList)
    {
        $accessList = array_map(function($ip) { return new ProjectAccess($ip); }, $accessList);

        return $this->createForm('project_access_delete', array('ip' => $accessList), [
            'action' => $this->generateUrl('app_core_project_access_delete', ['id' => $project->getId()]),
        ]);
    }

    private function getMasterPasswordForm(Project $project)
    {
        return $this->createForm('project_master_password', $project, [
            'action' => $this->generateUrl('app_core_project_master_password_update', ['id' => $project->getId()]),
            'method' => 'POST',
        ]);
    }

    private function getProjectAccessList(Project $project)
    {
        return $this
            ->get('app_core.redis')
            ->smembers('auth:' . $project->getSlug());
    }

    private function grantProjectAccess(Project $project, ProjectAccess $access)
    {
        return $this
            ->get('app_core.redis')
            ->sadd('auth:' . $project->getSlug(), $access->getIp());
    }

    private function revokeProjectAccess(Project $project, ProjectAccess $access)
    {
        return $this
            ->get('app_core.redis')
            ->srem('auth:' . $project->getSlug(), $access->getIp());
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

        return $this->render('AppCoreBundle:Project:auth.html.twig', [
            'project' => $project,
            'return' => $request->query->get('return'),
        ]);
    }

    public function authorizeAction(Request $request, $slug)
    {
        $project = $this->findProjectBySlug($slug);

        $flashBag = $request->getSession()->getFlashBag();

        if ($request->request->get('password') === 'passroot') {
            # @todo fix this
            if (preg_match('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $_SERVER['HTTP_X_FORWARDED_FOR'], $matches)) {
                $this
                    ->get('app_core.redis')
                    ->sadd('auth:'.$project->getSlug(), $matches[0]);

                if (strlen($return = $request->request->get('return')) > 0) {
                    return $this->redirect('http://' . $request->request->get('return'));
                }
    
                $flashBag->add('success', 'You have been authenticated');
            } else {
                $flashBag->add('error', 'Unable to find your IP address');
            }
        }

        return $this->render('AppCoreBundle:Project:auth.html.twig', [
            'project' => $project,
            'return' => $request->request->get('return'),
        ]);
    }
}