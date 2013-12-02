<?php

namespace App\CoreBundle\EventListener\Build;

use App\CoreBundle\Event\BuildFinishedEvent;

use Psr\Log\LoggerInterface;

use Redis;

class BuildRoutingListener
{
    private $redis;

    private $buildHostMask;

    public function __construct(LoggerInterface $logger, Redis $redis, $buildHostMask)
    {
        $this->redis = $redis;
        $this->buildHostMask = $buildHostMask;

        $logger->info('initialized '.__CLASS__);
    }

    public function onBuildFinished(BuildFinishedEvent $event)
    {
        $build = $event->getBuild();


        if (!$build->isRunning()) {
            return;
        }

        if (strlen($build->getHost()) === 0) {
            $build->setHost(sprintf($this->buildHostMask, $build->getBranchDomain()));
        }

        $redis = $this->redis;

        $build_redis_list = 'frontend:'.$build->getHost();

        $redis->del($build_redis_list);
        $redis->rpush($build_redis_list, $build->getImageName(), 'http://127.0.0.1:'.$build->getPort());
    }
}