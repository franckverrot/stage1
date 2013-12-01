<?php

namespace App\CoreBundle\EventListener\Build;

use App\CoreBundle\Event\BuildFinishedEvent;

use Psr\Log\LoggerInterface;

class BuildRoutingListener
{
    private $buildHostMask;

    public function __construct(LoggerInterface $logger, $buildHostMask)
    {
        $this->buildHostMask = $buildHostMask;

        $logger->info('initialized '.__CLASS__);
    }

    public function onBuildFinished(BuildFinishedEvent $event)
    {
        $build = $event->getBuild();

        if ($build->isRunning() && strlen($build->getHost()) === 0) {
            $build->setHost(sprintf($this->buildHostMask, $build->getBranchDomain()));
        }
    }
}