<?php

namespace App\CoreBundle\EventListener;

use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

use Redis;

class ProjectAccessTableListener
{
    private $redis;
    private $session;

    public function __construct(Redis $redis, SessionInterface $session)
    {
        $this->redis = $redis;
        $this->session = $session;
    }

    public function onLogin(InteractiveLoginEvent $event)
    {
        $token = $this->session->getId();
        $user = $event->getAuthenticationToken()->getUser();

        $project = $user->getProjects()->first();

        if ($this->redis->sismember('auth:'.$project->getSlug(), $token)) {
            # token still valid, do nothing
            return;
        }

        $this->redis->multi();

        foreach ($user->getProjects() as $project) {
            $this->redis->sadd('auth:'.$project->getSlug(), $token);
        }

        $this->redis->exec();
    }
}