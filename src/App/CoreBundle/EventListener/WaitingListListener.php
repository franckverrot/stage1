<?php

namespace App\CoreBundle\EventListener;

use Symfony\Component\Security\Core\SecurityContext;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Bundle\TwigBundle\Controller\ExceptionController;

use App\CoreBundle\Controller\WaitingListController;
use App\CoreBundle\Entity\User;

class WaitingListListener 
{
    public function __construct(WaitingListController $controller, SecurityContext $context)
    {
        $this->controller = $controller;
        $this->context = $context;
    }

    public function onKernelController(FilterControllerEvent $event)
    {
        $controller = $event->getController();

        $token = $this->context->getToken();

        if (null === $token) {
            return;
        }

        $user = $token->getUser();

        if (!is_object($user)) {
            return;
        }

        if (
            $user->getStatus() !== User::STATUS_WAITING_LIST ||
            !is_array($controller) ||
            $controller[0] instanceof WaitingListController
        ) {
            return;
        }

        $event->setController([$this->controller, 'indexAction']);
    }
}