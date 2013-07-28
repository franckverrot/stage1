<?php

namespace App\CoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Process\ProcessBuilder;

use App\CoreBundle\Entity\Project;
use App\CoreBundle\Entity\Build;

use Exception;
use DateTime;

class DefaultController extends Controller
{
    private function persistAndFlush($entity)
    {
        $em = $this->getDoctrine()->getManager();
        $em->persist($entity);
        $em->flush();
    }

    private function findBuild($id)
    {
        $build = $this->getDoctrine()->getRepository('AppCoreBundle:Build')->find($id);

        if (!$build) {
            throw $this->createHttpNotFoundException();
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
            throw $this->createHttpNotFoundException();
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

    public function buildCancelAction($id)
    {
        try {
            $build = $this->findBuild($id);
            $build->setStatus(Build::STATUS_CANCELED);

            $this->persistAndFlush($build);

            return new JsonResponse(null, 200);
        } catch (Exception $e) {
            return new JsonResponse(['message' => $e->getMessage()], 500);
        }
    }

    public function projectShowAction($id)
    {
        return $this->redirect($this->generateUrl('app_core_project_builds', ['id' => $id]));
    }

    public function projectBranchesAction($id)
    {
        $this->get('request')->attributes->set('current_project_id', $id);

        $project = $this->findProject($id);

        $pendingBuilds = [];

        foreach ($this->findPendingBuilds($project) as $build) {
            $pendingBuilds[$build->getRef()] = '#';
        }

        return $this->render('AppCoreBundle:Default:projectBranches.html.twig', [
            'project' => $project,
            'pending_builds' => $pendingBuilds,
        ]);        
    }

    public function projectBuildsAction($id)
    {
        $this->get('request')->attributes->set('current_project_id', $id);

        $project = $this->findProject($id);

        $qb = $this->getDoctrine()->getRepository('AppCoreBundle:Build')->createQueryBuilder('b');

        $builds = $qb
            ->where($qb->expr()->eq('b.project', ':project'))
            ->orderBy('b.createdAt', 'DESC')
            ->setParameter(':project', $project->getId())
            ->getQuery()
            ->execute();

        return $this->render('AppCoreBundle:Default:projectBuilds.html.twig', [
            'project' => $project,
            'builds' => $builds,
        ]);
    }

    public function projectScheduleBuildAction(Request $request, $id)
    {
        $project = $this->findProject($id);

        if (0 > count($this->findPendingBuilds($project))) {
            return new JsonResponse(['class' => 'warning', 'message' => 'You already have a build pending for this project']);
        }

        try {            
            $build = new Build();
            $build->setProject($project);
            $build->setInitiator($this->getUser());
            $build->setStatus(Build::STATUS_SCHEDULED);
            $build->setRef($request->request->get('ref'));
            $build->setHash($request->request->get('hash'));

            $now = new DateTime();
            $build->setCreatedAt($now);
            $build->setUpdatedAt($now);

            $this->persistAndFlush($build);

            $producer = $this->get('old_sound_rabbit_mq.build_producer');
            $producer->publish(json_encode(['build_id' => $build->getId()]));

            return new JsonResponse(['build_id' => $build->getId()], 201);
        } catch (Exception $e) {
            return new JsonResponse(['class' => 'danger', 'message' => $e->getMessage()], 500);
        }
    }

    public function projectDetectBranchesAction($id)
    {
        $project = $this->findProject($id);

        $builder = new ProcessBuilder(['/usr/bin/git', 'ls-remote', '--heads', $project->getGitUrl()]);
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
        ]);
    }

    public function projectImportAction(Request $request)
    {
        try {
            $project = new Project();
            $project->setOwner($this->getUser());
            $project->setName($request->request->get('name'));
            $project->setGitUrl($request->request->get('git_url'));

            $now = new DateTime();
            $project->setCreatedAt($now);
            $project->setUpdatedAt($now);

            $this->persistAndFlush($project);

            return new JsonResponse(['url' => $this->generateUrl('app_core_project_show', ['id' => $project->getId()]), 'project' => ['name' => $project->getName()]], 201);            
        } catch (Exception $e) {
            return new JsonResponse(['message' => $e->getMessage()], 500);
        }
    }
}
