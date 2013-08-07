<?php

namespace App\CoreBundle\EventListener;

use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Bundle\TwigBundle\Controller\ExceptionController;

use App\CoreBundle\Controller\ConfigureController;

class ConfigureListener 
{
    public function __construct($configured, ConfigureController $controller)
    {
        $this->configured = $configured;
        $this->controller = $controller;
    }

    public function onKernelController(FilterControllerEvent $event)
    {
        $controller = $event->getController();

        if (
            $this->configured ||
            !is_array($controller) ||
            $controller[0] instanceof ConfigureController ||
            $controller[0] instanceof ExceptionController
        ) {
            return;
        }

        $event->setController([$this->controller, 'captureAction']);
    }
}