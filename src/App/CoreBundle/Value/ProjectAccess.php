<?php

namespace App\CoreBundle\Value;

class ProjectAccess
{
    public $ip;

    public $token;

    public function __construct($ip = null, $token = null)
    {
        $this->ip = $ip;
        $this->token = $token;
    }

    public function getToken()
    {
        return $this->token;
    }

    public function setToken($token)
    {
        $this->token = $token;
        return $token;
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
}