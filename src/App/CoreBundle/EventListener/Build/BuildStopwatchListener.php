<?php

namespace App\CoreBundle\EventListener\Build;

use App\CoreBundle\Event\BuildStartedEvent;
use App\CoreBundle\Event\BuildFinishedEvent;

use Symfony\Component\Stopwatch\Stopwatch;

use Psr\Log\LoggerInterface;

use DateTime;

/**
 * Measures the time and memory consumption of a build
 */
class BuildStopwatchListener
{
    /**
     * @var Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var Symfony\Component\Stopwatch\Stopwatch
     */
    private $stopwatch;

    /**
     * @var Psr\Log\LoggerInterface
     * @param Symfony\Component\Stopwatch\Stopwatch $stopwatch
     */
    public function __construct(LoggerInterface $logger, Stopwatch $stopwatch)
    {
        $this->logger = $logger;
        $this->stopwatch = $stopwatch;

        $logger->info('initialized '.__CLASS__);
    }

    /**
     * @param App\CoreBundle\Event\BuildStartedEvent
     */
    public function onBuildStarted(BuildStartedEvent $event)
    {
        $build = $event->getBuild();

        $this->logger->info('starting stopwatch for build #'.$build->getId());
        $this->stopwatch->start($build->getChannel());

        $build->setStartTime(new DateTime());
    }
    /**
     * @param App\CoreBundle\Event\BuildFinishedEvent
     */
    public function onBuildFinished(BuildFinishedEvent $event)
    {
        $build = $event->getBuild();

        $this->logger->info('stopping stopwatch for build #'.$build->getId());
        $event = $this->stopwatch->stop($build->getChannel());

        $build->setEndTime(new DateTime());
        $build->setDuration($event->getDuration());
        $build->setMemoryUsage($event->getMemory());
    }
}