<?php

namespace App\CoreBundle\Event;

abstract class AbstractBuildEvent
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