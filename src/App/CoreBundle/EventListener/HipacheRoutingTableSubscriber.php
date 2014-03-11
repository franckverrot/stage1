<?php

namespace App\CoreBundle\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\Common\EventSubscriber;

use Psr\Log\LoggerInterface;

use App\Model\Project;

use Redis;

/**
 * This subscriber removes a project's hipache routing information
 * on project's removal
 */
class HipacheRoutingTableSubscriber implements EventSubscriber
{
    private $redis;

    public function __construct(Redis $redis, LoggerInterface $logger)
    {
        $this->redis = $redis;
        $this->logger = $logger;
    }

    public function getSubscribedEvents()
    {
        return ['preRemove'];
    }

    public function preRemove(LifecycleEventArgs $args)
    {
        $project = $args->getEntity();

        if (!$project instanceof Project) {
            return;
        }

        $this->logger->info('removing routing informations for "'.$project->getSlug().'"');

        $redis = $this->redis;

        $keys = $redis->keys('frontend:*'.$project->getSlug().'*');
        $redis->multi();

        foreach ($keys as $key) {
            $redis->del($key);
        }

        $redis->exec();
    }
}