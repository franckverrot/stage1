<?php

namespace App\CoreBundle\Controller;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

use App\CoreBundle\Entity\Build;
use App\CoreBundle\Entity\Project;
use App\CoreBundle\Entity\Demo;

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

        $session = $request->getSession();
        $session->set('demo_key', $session->get('demo_key', md5(uniqid(mt_rand(), true))));

        $channel = $session->get('demo_build_channel', 'demo-'.uniqid(mt_rand(), true));

        $build_id = $session->get('demo_build_id');

        if (null !== $build_id) {
            $build = $this->findBuild($build_id, false);

            if (!$build->isBuilding()) {
                $session->remove('demo_build_id');
                $channel = 'demo-'.uniqid(mt_rand(), true);
            }
        }

        $session->set('demo_build_channel', $channel);

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

        $email = $request->get('email');

        $errors = [];

        if (strlen($email) === 0) {
            $errors['email'] = 'Your e-mail is required.';
        }

        $session = $request->getSession();
        $session->set('demo_key', $session->get('demo_key', md5(uniqid(mt_rand(), true))));

        # find project without checking user
        $project = $this->findProject($request->get('project_id'), false);

        if (!$project || !$project->getUsers()->contains($this->getDemoUser())) {
            $errors['project_id'] = 'Invalid demo project.';
        }

        $echo = [
            'project_id' => var_export($request->get('project_id'), true),
            'email' => var_export($request->get('email'), true),
        ];

        if (count($errors) > 0) {
            return new JsonResponse(json_encode(['status' => 400, 'errors' => $errors, 'echo' => $echo]), 200);
        }

        $ref = $this->getDemoConfig('default_build_ref');
        $hash = $this->getHashFromRef($project, $ref, $this->getDemoConfig('access_token'));
        // $subdomain = substr(md5($request->getSession()->get('channel')), 0, 8);
        $subdomain = preg_replace('/[^a-z0-9\.]+/', '-', $email);

        $build = new Build();
        $build->setProject($project);
        $build->setInitiator($this->getDemoUser());
        $build->setStatus(Build::STATUS_SCHEDULED);
        $build->setRef($ref);
        $build->setHash($hash);
        $build->setChannel($session->get('demo_build_channel'));
        $build->setStreamOutput(true);
        $build->setStreamSteps(true);
        $build->setHost(sprintf($this->container->getParameter('build_host_mask'), $subdomain.'.demo'));
        $build->setIsDemo(true);

        $demo = new Demo();
        $demo->setEmail($email);
        $demo->setProject($project);
        $demo->setBuild($build);
        $demo->setDemoKey($session->get('demo_key'));

        $this->persistAndFlush($demo, $build);

        $request->getSession()->set('demo_build_id', $build->getId());

        $this->publishWebsocket('build.scheduled', $build->getChannel(), [
            'progress' => 0,
            'build' => $build->asWebsocketMessage(),
            'steps' => $this->getSteps($project),
            'project' => $project->asWebsocketMessage(),
        ]);

        $producer = $this->get('old_sound_rabbit_mq.build_producer');
        $producer->publish(json_encode(['build_id' => $build->getId()]));

        # @todo @channel_auth
        $websocket_token = uniqid(mt_rand(), true);
        $this->get('app_core.redis')->sadd('channel:auth:' . $build->getChannel(), $websocket_token);

        return new JsonResponse(json_encode(['status' => 200, 'echo' => $echo]), 200);
    }
}