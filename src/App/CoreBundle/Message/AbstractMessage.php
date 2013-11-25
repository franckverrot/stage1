<?php

namespace App\CoreBundle\Message;

use App\CoreBundle\Entity\Build;

abstract class AbstractMessage
{
    private $build;
    
    public function __construct(Build $build)
    {
        $this->build = $build;
    }
}