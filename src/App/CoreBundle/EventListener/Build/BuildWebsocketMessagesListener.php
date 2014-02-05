<?php

namespace App\CoreBundle\EventListener\Build;

use App\CoreBundle\Event\BuildStartedEvent;
use App\CoreBundle\Event\BuildFinishedEvent;
use App\CoreBundle\Event\BuildKilledEvent;

use App\CoreBundle\Message\MessageFactory;

use OldSound\RabbitMqBundle\RabbitMq\Producer;

use Psr\Log\LoggerInterface;

/**
 * Sends various lifecycle websocket messages
 */
class BuildWebsocketMessagesListener
{
    /**
     * @var Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var OldSound\RabbitMqBundle\RabbitMq\Producer
     */
    private $producer;

    /**
     * @var App\CoreBundle\Message\MessageFactory
     */
    private $factory;

    /**
     * @var Psr\Log\LoggerInterface
     * @var OldSound\RabbitMqBundle\RabbitMq\Producer
     */
    public function __construct(LoggerInterface $logger, Producer $producer, MessageFactory $factory)
    {
        $this->logger = $logger;
        $this->producer = $producer;
        $this->factory = $factory;

        $logger->info('initialized '.__CLASS__);
    }

    /**
     * @param App\CoreBundle\Event\BuildStartedEvent
     */
    public function onBuildStarted(BuildStartedEvent $event)
    {
        $build = $event->getBuild();

        /** @todo write a producer that accepts Message objects */
        $this->logger->info('sending build.started message', ['build' => $build->getId()]);
        $message = $this->factory->createBuildStarted($build);
        $this->producer->publish((string) $message);
    }

    /**
     * @param App\CoreBundle\Event\BuildFinishedEvent
     */
    public function onBuildFinished(BuildFinishedEvent $event)
    {
        $build = $event->getBuild();

        /** @todo write a producer that accepts Message objects */
        $this->logger->info('sending build.finished message', ['build' => $build->getId()]);
        $message = $this->factory->createBuildFinished($build);
        $this->producer->publish((string) $message);
    }

    /**
     * @param App\CoreBundle\Event\BuildFinishedEvent
     */
    public function onBuildKilled(BuildKilledEvent $event)
    {
        $build = $event->getBuild();

        /** @todo write a producer that accepts Message objects */
        $this->logger->info('sending build.killed message', ['build' => $build->getId()]);
        $message = $this->factory->createBuildKilled($build);
        $this->producer->publish((string) $message);
    }
}