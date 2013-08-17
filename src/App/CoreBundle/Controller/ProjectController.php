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

    public function masterPasswordUpdateAction(Request $request, $id)
    {
        $this->setCurrentProjectId($id);
        $project = $this->findProject($id);

        $form = $this->getMasterPasswordForm($project);

        $form->bind($request);

        if ($form->isValid()) {
            $project->setMasterPassword(password_hash($project->getMasterPassword(), PASSWORD_BCRYPT));
            $this->persistAndFlush($project);

            $request->getSession()->getFlashBag()->add('success', 'Master password updated');

            return $this->redirect($this->generateUrl('app_core_project_access', ['id' => $project->getId()]));
        }

        return $this->render('AppCoreBundle:Project:access.html.twig', [
            'project' => $project,
            'access_list' => $this->getProjectAccessList($project),
            'access_form' => $this->getAccessForm($project)->createView(),
            'master_password_form' => $form->createView(),
        ]);
    }

    public function accessAction($id)
    {
        $this->setCurrentProjectId($id);

        $project = $this->findProject($id);

        return $this->render('AppCoreBundle:Project:access.html.twig', [
            'project' => $project,
            'access_list' => $this->getProjectAccessList($project),
            'access_form' => $this->getAccessForm($project)->createView(),
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