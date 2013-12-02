<?php

namespace App\CoreBundle\EventListener\Build;

use App\CoreBundle\Event\BuildFinishedEvent;

use Psr\Log\LoggerInterface;

use Redis;

/**
 * App\CoreBundle\EventListener\Build\BuildRoutingListener
 */
class BuildRoutingListener
{
    /**
     * @var Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var Redis
     */
    private $redis;

    /**
     * @var string
     */
    private $buildHostMask;

    /**
     * @param Psr\Log\LoggerInterface $logger
     * @param Redis $redis
     * @param string $buildHostMask
     */
    public function __construct(LoggerInterface $logger, Redis $redis, $buildHostMask)
    {
        $this->logger = $logger;
        $this->redis = $redis;
        $this->buildHostMask = $buildHostMask;

        $logger->info('initialized '.__CLASS__);
    }

    /**
     * @param App\CoreBundle\Event\BuildFinishedEvent $event
     */
    public function onBuildFinished(BuildFinishedEvent $event)
    {
        $build = $event->getBuild();

        if (!$build->isRunning()) {
            return;
        }

        if (strlen($build->getHost()) === 0) {
            $build->setHost(sprintf($this->buildHostMask, $build->getBranchDomain()));
        }

        $this->logger->info('configuring build routing', ['build' => $build->getId(), 'host' => $build->getHost()]);

        $build_redis_list = 'frontend:'.$build->getHost();

        $this->redis->del($build_redis_list);
        $this->redis->rpush($build_redis_list, $build->getImageName(), 'http://127.0.0.1:'.$build->getPort());
    }
}