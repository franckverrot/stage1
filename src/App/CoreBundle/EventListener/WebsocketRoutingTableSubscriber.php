<?php

namespace App\CoreBundle\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\Common\EventSubscriber;

use App\CoreBundle\Entity\Project;

use Redis;

/**
 * This subscriber updates the websocket routing table whenever
 * a new Project or Build is saved or removed
 */
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

    private function getKeys($entity)
    {
        return [
            'entity' => $entity->getChannel(),
            'users' => $entity->getUsers()->map(function($user) {
                return 'channel:routing:'.$user->getChannel();
            })
        ];
    }

    public function postPersist(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();

        if (!method_exists($entity, 'getChannel') || !method_exists($entity, 'getUsers')) {
            return;
        }

        $keys = $this->getKeys($entity);
        $entityKey = $keys['entity'];

        foreach ($keys['users'] as $userKey) {
            $this->redis->sadd($userKey, $entityKey);
        }

    }

    public function preRemove(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();

        if (!$entity instanceof Project) {
            return;
        }

        $keys = $this->getKeys($entity);
        $entityKey = $keys['entity'];

        foreach ($keys['users'] as $userKey) {
            $this->redis->srem($userKey, $entityKey);
        }
    }
}