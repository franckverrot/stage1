<?php

namespace App\CoreBundle\Controller;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

use App\CoreBundle\Entity\Build;
use App\CoreBundle\Entity\Project;

use Exception;
use RuntimeException;

class DemoController extends Controller
{
    private $demoConfig;

    private $demoUser;

    public function getSteps(Project $project)
    {
        $steps = [
            [
                'id' => 'prepare_build_container',
                'label' => 'Preparing build container',
                'tooltip' => 'This is the container where your project will be built. This steps include ssh keys generation and setup, for example.'
            ],
            [
                'id' => 'clone_repository',
                'label' => 'Cloning repository',
                'tooltip' => 'Next, we need to retrieve your code from github. Pretty straightforward.'
            ],
            [
                'id' => 'select_builder',
                'label' => 'Selecting builder',
                'tooltip' => 'We need to determine what type of project you\'re trying to build (that is, if you did not specify a custom build). We use something much like heroku\'s buildpacks for that.',
            ],
            [
                'id' => 'install_dependencies',
                'label' => 'Installing dependencies',
                'tooltip' => 'Every project has dependencies, let\'s fetch them! For example, the Symfony 2 builder will use composer to install dependencies.',
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

    public function indexAction(Request $request)
    {
        if (false === $this->getDemoConfig('enabled')) {
            return $this->disabledAction();
        }

        $channel = 'demo-'.uniqid(mt_rand(), true);

        $request->getSession()->set('channel', $channel);

        $config = $this->getDemoConfig();

        foreach ($this->getDemoConfig('projects') as $project) {
            # @todo @slug
            $slugs[] = preg_replace('/[^a-z0-9\-]/', '-', strtolower($project));
        }

        $projects = $this->getDoctrine()->getRepository('AppCoreBundle:Project')->findBySlug($slugs);

        if (count($projects) === 0) {
            throw new RuntimeException('No demo projects found');
        }

        $websocketChannels = [];

        foreach ($projects as $project) {
            $websocketChannels[] = $project->getChannel();
        }

        $redis = $this->container->get('app_core.redis');
        $redis->del('channel:routing:'.$channel);

        array_unshift($websocketChannels, 'channel:routing:'.$channel);
        call_user_func_array(array($redis, 'sadd'), $websocketChannels);

        return $this->render('AppCoreBundle:Demo:index.html.twig', [
            'projects' => $projects,
            'channel' => $channel,
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
        $build->setChannel($request->getSession()->get('channel'));
        $build->setStreamOutput(true);
        $build->setStreamSteps(true);

        $this->persistAndFlush($build);

        $this->publishWebsocket('build.scheduled', $build->getChannel(), [
            'build' => $build->asWebsocketMessage(),
            'steps' => $this->getSteps($project),
            'project' => $project->asWebsocketMessage(),
        ]);

        $producer = $this->get('old_sound_rabbit_mq.build_producer');
        $producer->publish(json_encode(['build_id' => $build->getId()]));

        # @todo @channel_auth
        $websocket_token = uniqid(mt_rand(), true);
        $this->get('app_core.redis')->sadd('channel:auth:' . $build->getChannel(), $websocket_token);

        return new JsonResponse(json_encode(true), 200);
    }
}