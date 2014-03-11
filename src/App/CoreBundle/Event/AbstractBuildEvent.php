<?php

namespace App\CoreBundle\Event;

use App\Model\Build;

use Symfony\Component\EventDispatcher\Event;

abstract class AbstractBuildEvent extends Event
{
    private $build;
    
    public function __construct(Build $build)
    {
        $this->build = $build;
    }

    public function getBuild()
    {
        return $this->build;
    }
}