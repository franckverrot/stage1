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

use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;

use Psr\Log\LoggerInterface;

use Exception;

class BuildConsumer implements ConsumerInterface
{
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

        $logger->info('initialized '.__CLASS__);
    }

    public function execute(AMQPMessage $message)
    {
        $body = json_decode($message->body);

        $em = $this->doctrine->getManager();
        $buildRepository = $em->getRepository('AppCoreBundle:Build');

        $build = $buildRepository->find($body->build_id);

        if (!$build) {
            $this->logger->info('could not find build #'.$body->build_id);
            return;
        }

        $build->setStatus(Build::STATUS_BUILDING);

        $em->persist($build);
        $em->flush();

        try {
            $this->dispatcher->dispatch(BuildEvents::STARTED, new BuildStartedEvent($build));

            $container = $this->builder->run($build);

            $build->setContainerId($container->getId());
            $build->setPort($container->getMappedPort(80)->getHostPort());

            $previousBuild = $buildRepository->findPreviousBuild($build);

            if ($previousBuild && $previousBuild->hasContainer()) {
                $this->docker->getContainerManager()->stop($previousBuild->getContainer());
                $previousBuild->setStatus(Build::STATUS_OBSOLETE);
                $em->persist($previousBuild);
            }

            if (strlen($build->getHost()) === 0) {
                $build->setHost(sprintf($this->buildHostMask, $build->getBranchDomain()));
            }

            $build->setStatus(Build::STATUS_RUNNING);

            $this->dispatcher->dispatch(BuildEvents::FINISHED, new BuildFinishedEvent($build));            
        } catch (Exception $e) {
            $this->logger->error('Build failed', ['exception' => $e]);
            $build->setStatus(Build::STATUS_FAILED);
            $build->setMessage(get_class($e).': '.$e->getMessage());
        }

        $em->persist($build);
        $em->flush();
    }
}