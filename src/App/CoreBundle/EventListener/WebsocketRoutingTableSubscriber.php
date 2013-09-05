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
        return [
            'project' => $entity->getChannel(),
            'users' => $entity->getUsers()->map(function($user) {
                return 'channel:routing:'.$user->getChannel();
            })
        ];
    }

    public function postPersist(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();

        if (!$entity instanceof Project) {
            return;
        }

        $keys = $this->getKeys($entity);
        $projectKey = $keys['project'];

        foreach ($keys['users'] as $userKey) {
            $this->redis->sadd($userKey, $projectKey);
        }

    }

    public function preRemove(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();

        if (!$entity instanceof Project) {
            return;
        }

        $keys = $this->getKeys($entity);
        $projectKey = $keys['project'];

        foreach ($keys['users'] as $userKey) {
            $this->redis->srem($userKey, $projectKey);
        }
    }
}