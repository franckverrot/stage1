<?php

namespace App\CoreBundle\Consumer;

use App\Model\Build;
use App\Model\Project;
use App\CoreBundle\Message\MessageFactory;

use Docker\Docker;

use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use OldSound\RabbitMqBundle\RabbitMq\Producer;
use PhpAmqpLib\Message\AMQPMessage;

use Psr\Log\LoggerInterface;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Bundle\FrameworkBundle\Routing\Router;

use Exception;

class KillConsumer implements ConsumerInterface
{
    private $logger;

    private $docker;

    private $doctrine;

    private $factory;

    private $producer;

    private $router;

    private $timeout = 10;

    public function __construct(LoggerInterface $logger, Docker $docker, RegistryInterface $doctrine, MessageFactory $factory, Producer $producer, Router $router)
    {
        $this->logger = $logger;
        $this->docker = $docker;
        $this->doctrine = $doctrine;
        $this->factory = $factory;
        $this->producer = $producer;
        $this->router = $router;

        $logger->info('initialized '.__CLASS__);
    }

    public function execute(AMQPMessage $message)
    {
        $logger = $this->logger;
        $body = json_decode($message->body);

        $logger->info('received kill order', ['build' => $body->build_id]);

        $buildRepo = $this->doctrine->getRepository('Model:Build');
        $build = $buildRepo->find($body->build_id);

        if (!$build) {
            $logger->warn('could not find build', ['build' => $body->build_id]);
            return;
        }

        $build->setStatus(Build::STATUS_KILLED);

        $em = $this->doctrine->getManager();
        $em->persist($build);
        $em->flush();

        $terminated = true;

        if ($build->getPid()) {

            if (false === posix_kill($build->getPid(), SIGTERM)) {
                $logger->info('build found but pid does not exist, marking as killed', ['build' => $build->getId(), 'pid' => $build->getPid()]);
            } else {
                $logger->info('pid found, sent SIGTERM', ['build' => $build->getId(), 'pid' => $build->getPid()]);

                $terminated = false;

                for ($i = 0; $i <= $this->timeout; $i++) {
                    if (false === posix_kill($build->getPid(), 0)) {
                        $terminated = true;
                        break;
                    }

                    $logger->info('build still alive...', ['build' => $build->getId(), 'pid' => $build->getPid()]);

                    sleep(1);
                }
            }
        }

        if (null === $container = $build->getContainer()) {
            $logger->warn('could not find a container');
        } else {
            $logger->info('trying to stop container', [
                'build' => $build->getId(),
                'container' => $container->getId()
            ]);

            try {            
                $this->docker->getContainerManager()->stop($container);
            } catch (UnexpectedStatusCodeException $e) {
                if ($e->getCode() !== 404) {
                    throw $e;
                }
            }
        }

        if (!$terminated) {
            $logger->info('sending SIGKILL', ['build' => $build->getId(), 'pid' => $build->getPid()]);
            posix_kill($build->getPid(), SIGKILL);
        }

        $this->producer->publish((string) $this->factory->createBuildKilled($build));
    }
}