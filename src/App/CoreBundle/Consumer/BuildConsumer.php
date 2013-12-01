<?php

namespace App\CoreBundle\Consumer;

use Symfony\Bridge\Doctrine\RegistryInterface;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use App\CoreBundle\Builder\Builder;
use App\CoreBundle\Entity\Build;
use App\CoreBundle\Message\BuildStartedMessage;
use App\CoreBundle\Message\BuildFinishedMessage;
use App\CoreBundle\Message\BuildStepMessage;
use App\CoreBundle\BuildEvents;
use App\CoreBundle\Event\BuildStartedEvent;
use App\CoreBundle\Event\BuildFinishedEvent;
use App\CoreBundle\Event\BuildKilledEvent;

use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;

use Psr\Log\LoggerInterface;

use Docker\Docker;
use Docker\Container;
use Docker\Exception\ContainerNotFoundException;

use Exception;

declare(ticks = 1);

class BuildConsumer implements ConsumerInterface
{
    private $build;

    private $doctrine;

    private $producer;

    private $router;

    private $docker;

    private $buildTimeout = 0;

    private $buildHostMask;

    public function __construct(LoggerInterface $logger, EventDispatcherInterface $dispatcher, RegistryInterface $doctrine, Builder $builder, Docker $docker, $buildHostMask)
    {
        $this->logger = $logger;
        $this->dispatcher = $dispatcher;
        $this->doctrine = $doctrine;
        $this->builder = $builder;
        $this->docker = $docker;
        $this->buildHostMask = $buildHostMask;

        pcntl_signal(SIGTERM, [$this, 'terminate']);

        $logger->info('initialized '.__CLASS__);
    }

    public function terminate($signo)
    {
        $this->logger->info('received signal', ['signo' => $signo]);

        if (null !== $this->build) {
            $build = $this->build;
            $docker = $this->docker;

            $this->logger->info('[terminate] build hash', ['build' => spl_object_hash($build)]);

            $this->logger->info('cleaning things before exiting', ['build' => $build->getId()]);

            if (($container = $build->getContainer()) instanceof Container) {
                $this->logger->info('stopping container', [
                    'build' => $build->getId(),
                    'container' => $container->getId(),
                ]);

                $docker->getContainerManager()->stop($container);
            } else {
                var_dump($container);
            }

            $build->setStatus(Build::STATUS_KILLED);
            $build->setMessage('Build terminated with signal '.$signo);

            $em = $this->doctrine->getManager();
            $em->persist($build);
            $em->flush();

            $this->dispatcher->dispatch(BuildEvents::KILLED, new BuildKilledEvent($build));
        }

        exit(0);
    }

    public function execute(AMQPMessage $message)
    {
        $body = json_decode($message->body);

        $em = $this->doctrine->getManager();
        $buildRepository = $em->getRepository('AppCoreBundle:Build');

        $this->build = $build = $buildRepository->find($body->build_id);

        if (!$build) {
            $this->logger->info('could not find build #'.$body->build_id);
            return;
        }

        if (!$build->isScheduled()) {
            $this->logger->warn('build found but not scheduled', [
                'build' => $build->getId(),
                'status' => $build->getStatus(),
                'status_label' => $build->getStatusLabel()
            ]);

            return;
        }

        $build->setStatus(Build::STATUS_BUILDING);
        $build->setPid(posix_getpid());

        $em->persist($build);
        $em->flush();

        try {
            $this->dispatcher->dispatch(BuildEvents::STARTED, new BuildStartedEvent($build));

            $container = $this->builder->run($build);

            $build->setContainer($container);
            $build->setPort($container->getMappedPort(80)->getHostPort());

            $previousBuild = $buildRepository->findPreviousBuild($build);

            // @todo move to a build.finished listener
            if ($previousBuild && $previousBuild->hasContainer()) {
                try {
                    $this->docker->getContainerManager()->stop($previousBuild->getContainer());
                } catch (ContainerNotFoundException $e) {
                    $this->logger->warn('Found previous container but docker did not find it', ['container' => $previousBuild->getContainer()->getId()]);
                }
                $previousBuild->setStatus(Build::STATUS_OBSOLETE);
                $em->persist($previousBuild);
            }

            // @todo move to a build.finished listener
            if (strlen($build->getHost()) === 0) {
                $build->setHost(sprintf($this->buildHostMask, $build->getBranchDomain()));
            }

            $build->setStatus(Build::STATUS_RUNNING);
        } catch (Exception $e) {
            $this->logger->error('build failed', ['build' => $build->getId(), 'exception' => $e]);
            $build->setStatus(Build::STATUS_FAILED);
            $build->setMessage(get_class($e).': '.$e->getMessage());
        }

        /**
         * We run this in a separate try/catch because even if the main build fails
         * we want to let listeners a chance to do something
         */
        try {
            $this->dispatcher->dispatch(BuildEvents::FINISHED, new BuildFinishedEvent($build));            
        } catch (Exception $e) {
            $this->logger->error('build.finished listeners failed', ['build' => $build->getId(), 'exception' => $e]);
            $build->setStatus(Build::STATUS_FAILED);
            $build->setMessage(get_class($e).': '.$e->getMessage());            
        }

        $em->persist($build);
        $em->flush();
    }
}