<?php

namespace App\CoreBundle\Value;

class ProjectAccess
{
    public $ip;

    public function __construct($ip = null)
    {
        $this->ip = $ip;
    }

    public function getIp()
    {
        return $this->ip;
    }

    public function setIp($ip)
    {
        $this->ip = $ip;
        return $this;
    }

    public function __toString()
    {
        return $this->ip;
    }
}