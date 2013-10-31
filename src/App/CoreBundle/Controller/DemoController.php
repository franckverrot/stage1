<?php

namespace App\CoreBundle\Controller;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

use App\CoreBundle\Entity\Build;
use App\CoreBundle\Entity\Project;

use Exception;

class DemoController extends Controller
{
    private $demoConfig;

    private $demoUser;

    public function getSteps(Project $project)
    {
        $steps = [
            [
                'id' => 'prepare_build_container',
                'label' => 'Preparing build container'
            ],
            [
                'id' => 'clone_repository',
                'label' => 'Cloning repository'
            ],
            [
                'id' => 'select_builder',
                'label' => 'Selecting builder'
            ],
            [
                'id' => 'install_dependencies',
                'label' => 'Installing dependencies'
            ],
        ];

        return $steps;
    }

    private function getDemoUser()
    {
        if (null === $this->demoUser) {
            $this->demoUser = $this->findUserByUsername($this->getDemoConfig('username'));
        }

        return $this->demoUser;
    }

    private function getDemoConfig($key = null)
    {
        if (null === $this->demoConfig) {
            $this->demoConfig = Yaml::parse($this->container->getParameter('kernel.root_dir').'/config/demo.yml');
        }

        if (null !== $key) {
            return $this->demoConfig[$key];
        }

        return $this->demoConfig;
    }

    public function disabledAction()
    {
        return $this->render('AppCoreBundle:Demo:disabled.html.twig');
    }

    public function indexAction()
    {
        if (false === $this->getDemoConfig('enabled')) {
            return $this->disabledAction();
        }

        $config = $this->getDemoConfig();

        foreach ($this->getDemoConfig('projects') as $project) {
            # @todo @slug
            $slugs[] = preg_replace('/[^a-z0-9\-]/', '-', strtolower($project));
        }

        $projects = $this->getDoctrine()->getRepository('AppCoreBundle:Project')->findBySlug($slugs);

        return $this->render('AppCoreBundle:Demo:index.html.twig', [
            'projects' => $projects,
        ]);
    }

    public function buildAction(Request $request)
    {
        if (false === $this->getDemoConfig('enabled')) {
            return $this->disabledAction();
        }

        # find project without checking user
        $project = $this->findProject($request->get('project_id'), false);

        if (!$project->getUsers()->contains($this->getDemoUser())) {
            throw new Exception('Not a Demo project');
        }

        $ref = $this->getDemoConfig('default_build_ref');
        $hash = $this->getHashFromRef($project, $ref, $this->getDemoConfig('access_token'));

        $build = new Build();
        $build->setProject($project);
        $build->setInitiator($this->getDemoUser());
        $build->setStatus(Build::STATUS_SCHEDULED);
        $build->setRef($ref);
        $build->setHash($hash);

        $this->persistAndFlush($build);

        $producer = $this->get('old_sound_rabbit_mq.build_producer');
        $producer->publish(json_encode(['build_id' => $build->getId()]));

        # @todo @channel_auth
        $websocket_token = uniqid(mt_rand(), true);
        $this->get('app_core.redis')->sadd('channel:auth:' . $build->getChannel(), $websocket_token);

        return new JsonResponse(json_encode([
            'project' => $project->asWebsocketMessage(),
            'build' => $build->asWebsocketMessage(),
            'steps' => $this->getSteps($project),
            'websocket_channel' => $build->getChannel(),
            'websocket_token' => $websocket_token,
        ]));
    }
}