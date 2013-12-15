<?php

namespace App\CoreBundle\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\Common\EventSubscriber;

use App\CoreBundle\Entity\Project;
use App\CoreBundle\Entity\User;
use App\CoreBundle\Entity\WebsocketRoutable;

use Psr\Log\LoggerInterface;

use Redis;

/**
 * This subscriber updates the websocket routing table whenever
 * a new Project or Build is saved or removed
 */
class WebsocketRoutingTableSubscriber implements EventSubscriber
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
     * @param Psr\Log\LoggerInterface   $logger
     * @param Redis                     $redis
     */
    public function __construct(LoggerInterface $logger, Redis $redis)
    {
        $this->logger = $logger;
        $this->redis = $redis;
    }

    /**
     * @return array
     */
    public function getSubscribedEvents()
    {
        return [
            'postPersist',
            'preRemove',
        ];
    }

    /**
     * @param App\CoreBundle\Entity\WebsocketRoutable
     * 
     * @return array
     */
    private function getChannels(WebsocketRoutable $entity)
    {
        return [
            'entity' => $entity->getChannel(),
            'users' => $entity->getUsers()->map(function(User $user) {
                return 'channel:routing:'.$user->getChannel();
            })
        ];
    }

    /**
     * @param Doctrine\ORM\Event\LifecycleEventArgs
     */
    public function postPersist(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();

        if (!$entity instanceof WebsocketRoutable) {
            return;
        }

        $channels = $this->getChannels($entity);
        $redis = $this->redis;

        foreach ($channels['users'] as $channel) {
            $this->logger->info('adding websocket routing', [
                'user' => substr($channel, 16),
                'entity' => $channels['entity']
            ]);

            $redis->sadd($channel, $channels['entity']);
        }
    }

    /**
     * @param Doctrine\ORM\Event\LifecycleEventArgs
     */
    public function preRemove(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();

        if (!$entity instanceof Project) {
            return;
        }

        $channels = $this->getChannels($entity);
        $redis = $this->redis;

        foreach ($channels['users'] as $channel) {
            $this->logger->info('removing websocket routing', [
                'user' => substr($channel, 16),
                'entity' => $channels['entity']
            ]);

            $redis->srem($channel, $channels['entity']);
        }
    }
}