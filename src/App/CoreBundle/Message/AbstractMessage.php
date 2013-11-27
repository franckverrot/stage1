<?php

namespace App\CoreBundle\Message;

use App\CoreBundle\Entity\Build;

use Exception;

abstract class AbstractMessage
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

    public function __toString()
    {
        try {
            return json_encode($this->toArray());
        } catch (Exception $e) {
            echo $e->getMessage();
            return get_class($e).': '.$e->getMessage();
        }
    }

    abstract public function toArray();
}