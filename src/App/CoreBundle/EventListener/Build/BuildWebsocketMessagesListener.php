<?php

namespace App\CoreBundle\EventListener\Build;

use App\CoreBundle\Event\BuildStartedEvent;
use App\CoreBundle\Event\BuildFinishedEvent;

use App\CoreBundle\Message\BuildStartedMessage;
use App\CoreBundle\Message\BuildFinishedMessage;

use OldSound\RabbitMqBundle\RabbitMq\Producer;

use Psr\Log\LoggerInterface;

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
     * @var Psr\Log\LoggerInterface
     * @var OldSound\RabbitMqBundle\RabbitMq\Producer
     */
    public function __construct(LoggerInterface $logger, Producer $producer)
    {
        $this->logger = $logger;
        $this->producer = $producer;

        $logger->info('initialized '.__CLASS__);
    }

    /**
     * @param App\CoreBundle\Event\BuildStartedEvent
     */
    public function onBuildStarted(BuildStartedEvent $event)
    {
        $build = $event->getBuild();

        /** @todo write a producer that accepts Message objects */
        $this->logger->info('sending build.started message for build #'.$build->getId());
        $message = new BuildStartedMessage($build);
        $this->producer->publish((string) $message);
    }

    /**
     * @param App\CoreBundle\Event\BuildFinishedEvent
     */
    public function onBuildFinished(BuildFinishedEvent $event)
    {
        $build = $event->getBuild();

        /** @todo write a producer that accepts Message objects */
        $this->logger->info('sending build.finished message for build #'.$build->getId());
        $message = new BuildFinishedMessage($build);
        $this->producer->publish((string) $message);
    }
}