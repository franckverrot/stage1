<?php

namespace App\CoreBundle\Builder\Strategy;

use App\CoreBundle\Message\BuildMessage;
use App\Model\Build;
use App\Model\BuildScript;
use Docker\Docker;
use Docker\Context\Context;
use Doctrine\Common\Persistence\ObjectManager;
use Psr\Log\LoggerInterface;
use OldSound\RabbitMqBundle\RabbitMq\Producer;
use Symfony\Component\Process\ProcessBuilder;

class DockerfileStrategy
{
    private $logger;

    private $docker;

    private $objectManager;

    private $websocketProducer;

    private $options = [];

    public function __construct(LoggerInterface $logger, Docker $docker, ObjectManager $objectManager, Producer $websocketProducer, array $options)
    {
        $this->logger = $logger;
        $this->docker = $docker;
        $this->objectManager = $objectManager;
        $this->websocketProducer = $websocketProducer;
        $this->options = $options;
    }

    public function getOption($name)
    {
        return $this->options[$name];
    }

    public function getCmd()
    {
        return [];
    }

    public function build(Build $build, BuildScript $script, $timeout)
    {
        $logger = $this->logger;
        $docker = $this->docker;
        $websocketProducer = $this->websocketProducer;

        $publish = function ($content) use ($build, $websocketProducer) {
            $message = new BuildMessage($build, $content);
            $websocketProducer->publish((string) $message);
        };

        $project = $build->getProject();
        $options = $build->getOptions();

        $workdir = sys_get_temp_dir().'/stage1/workdir/'.$build->getId();

        if (!is_dir($workdir)) {
            mkdir($workdir, 0777, true);
        }

        $logger->info('using workdir', ['workdir' => $workdir]);

        mkdir($workdir.'/ssh');

        $project->dumpSshKeys($workdir.'/ssh', 'root');
        file_put_contents($workdir.'/ssh/config', $project->getSshConfig($workdir.'/ssh'));

        $clone =
            ProcessBuilder::create(['git', 'clone', $project->getGitUrl(), $workdir.'/source'])
            ->setEnv('GIT_SSH', '/usr/bin/ssh -F '.$workdir.'/ssh/config')
            ->getProcess();

        $publish('$ '.$clone->getCommandLine().PHP_EOL);

        $logger->info('cloning repository', ['command_line' => $clone->getCommandLine()]);

        $clone->run();

        $contextPath = $workdir.'/source/'.$options['dockerfile']['path'];

        $logger->info('creating docker build context from path', [
            'context_path' => $contextPath,
        ]);

        $context = new Context($contextPath);

        $logger->info('starting actual build', [
            'build' => $build->getId(),
            'timeout' => $timeout,
        ]);

        $response = $docker->build($context, $build->getImageName(), false, false, true, false);

        $error = false;

        $response->read(function($output) use ($logger, $response, $publish, &$error) {
            if ($response->headers->get('content-type') === 'application/json') {
                $output = json_decode($output, true);
                $logger->info('got data chunk', ['output' => $output]);
                $publish($output['stream']);
            } else {
                $message = $output;
            }
        });
    }
}