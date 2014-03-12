<?php

namespace App\CoreBundle\EventListener\Build;

use App\CoreBundle\Event\BuildFinishedEvent;

use Psr\Log\LoggerInterface;

use Redis;

/**
 * App\CoreBundle\EventListener\Build\BuildRoutingListener
 * 
 * Updates the routing table after a build
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
    public function __construct(LoggerInterface $logger, Redis $redis, $buildHostMask, $builderIp)
    {
        $this->logger = $logger;
        $this->redis = $redis;
        $this->buildHostMask = $buildHostMask;
        $this->builderIp = $builderIp;

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

        $container_url = 'http://'.$this->builderIp.':'.$build->getPort();

        $this->logger->info('configuring build instance routing', [
            'build' => $build->getId(),
            'backend' => $container_url
        ]);

        $redis = $this->redis;

        $redis->multi();
        $redis->del('frontend:'.$build->getHost());
        $redis->rpush('frontend:'.$build->getHost(), $build->getImageName(), $container_url);

        $urls = $build->getOption('urls', []);

        if (count($urls) > 0) {
            $this->logger->info('adding custom build URLs', ['build' => $build->getId(), 'domains' => $urls]);

            foreach ($urls as $domain) {
                $host = $domain.'.'.$build->getHost();
                $redis->del('frontend:'.$host);
                $redis->rpush('frontend:'.$host, $build->getImageName(), $container_url);
            }

            $redis->exec();
        }
    }
}