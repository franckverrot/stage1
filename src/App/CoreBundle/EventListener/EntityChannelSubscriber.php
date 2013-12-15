<?php

namespace App\CoreBundle\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\Common\EventSubscriber;

class EntityChannelSubscriber implements EventSubscriber
{
    private $environment;

    public function __construct($environment)
    {
        $this->environment = $environment;
    }

    public function getSubscribedEvents()
    {
        return ['prePersist'];
    }

    public function prePersist(LifecycleEventArgs $args)
    {
        // @todo it would be better to only declare this listener in prod
        //       instead of checking at runtime
        // if ($this->environment === 'dev') {
        //     return;
        // }

        $entity = $args->getEntity();

        // @todo this is because Builds have a setChannel too
        //       but we do want them to use their project's channel
        //       which is the default if they don't have a channel
        if (!$entity instanceof User) {
            return;
        }

        if (method_exists($entity, 'setChannel')) {
            $channel = uniqid(mt_rand(), true);
            $entity->setChannel($channel);
        }
    }
}