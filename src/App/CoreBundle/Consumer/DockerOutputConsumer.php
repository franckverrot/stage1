<?php

namespace App\CoreBundle\Consumer;

use Symfony\Bridge\Doctrine\RegistryInterface;

use App\CoreBundle\Entity\BuildLog;
use App\CoreBundle\Message\BuildLogMessage;

use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use OldSound\RabbitMqBundle\RabbitMq\Producer;
use PhpAmqpLib\Message\AMQPMessage;

use Psr\Log\LoggerInterface;

use Docker\Docker;

use DateTime;

class DockerOutputConsumer implements ConsumerInterface
{
    private $logger;

    private $doctrine;

    private $docker;

    public function __construct(LoggerInterface $logger, RegistryInterface $doctrine, Docker $docker, Producer $producer)
    {
        $this->logger = $logger;
        $this->doctrine = $doctrine;
        $this->docker = $docker;
        $this->producer = $producer;
    }

    public function execute(AMQPMessage $message)
    {
        $logger = $this->logger;

        $body = json_decode($message->body, true);
        $container = $this->docker->getContainerManager()->find($body['container']);
        $env = $container->getParsedEnv();

        if (!array_key_exists('BUILD_ID', $env)) {
            $logger->debug('discarding non-build container log', [
                'container' => $container->getId()
            ]);

            return;
        }

        $em = $this->doctrine->getManager();
        $repo = $em->getRepository('AppCoreBundle:Build');
        $build = $repo->find($env['BUILD_ID']);

        if (!$build) {
            $logger->warn('could not find build for container', [
                'build' => $env['BUILD_ID'],
                'container' => $container->getId()
            ]);

            return;
        }

        $logger->debug('processing log line', [
            'build' => $build->getId(),
            'container' => $container->getId(),
        ]);

        $streamMap = [0 => 'stdin', 1 => 'stdout', 2 => 'stderr'];

        $buildLog = new BuildLog();
        $buildLog->setType('output');
        $buildLog->setMessage($body['line']);
        $buildLog->setStream($body['type'] ? $streamMap[$body['type']] : null);
        $buildLog->setBuild($build);

        $build->addLog($buildLog);

        $em->persist($build);
        $em->persist($buildLog);

        $em->flush();

        $message = new BuildLogMessage($buildLog);
        $this->producer->publish((string) $message);
    }
}