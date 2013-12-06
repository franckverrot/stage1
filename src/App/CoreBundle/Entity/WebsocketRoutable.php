<?php

namespace App\CoreBundle\Entity;

interface WebsocketRoutable
{
    public function getChannel();

    public function getUsers();
}