<?php

namespace App\CoreBundle\Value;

class ProjectAccess
{
    public $ip;

    public function getIp()
    {
        return $this->ip;
    }

    public function setIp($ip)
    {
        $this->ip = $ip;
        return $this;
    }
}