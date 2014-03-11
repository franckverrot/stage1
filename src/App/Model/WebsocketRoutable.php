<?php

namespace App\Model;

interface WebsocketRoutable
{
    public function getChannel();

    public function getUsers();
}