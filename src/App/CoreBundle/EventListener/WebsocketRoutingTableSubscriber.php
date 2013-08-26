<?php

namespace App\CoreBundle\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\Common\EventSubscriber;

use App\CoreBundle\Entity\Project;

use Redis;

class WebsocketRoutingTableSubscriber implements EventSubscriber
{
    private $redis;

    public function __construct(Redis $redis)
    {
        $this->redis = $redis;
    }

    public function getSubscribedEvents()
    {
        return [
            'postPersist',
            'preRemove',
        ];
    }

    private function getKeys(Project $entity)
    {
        $userKey = sprintf('channel:routing:user.%d', $entity->getOwner()->getId());
        $projectKey = sprintf('project.%d', $entity->getId());

        return [$userKey, $projectKey];
    }

    public function postPersist(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();

        if (!$entity instanceof Project) {
            return;
        }

        list($userKey, $projectKey) = $this->getKeys($entity);

        $this->redis->sadd($userKey, $projectKey);
    }

    public function preRemove(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();

        if (!$entity instanceof Project) {
            return;
        }

        list($userKey, $projectKey) = $this->getKeys($entity);

        $this->redis->srem($userKey, $projectKey);
    }
}