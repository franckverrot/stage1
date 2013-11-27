<?php

namespace App\CoreBundle\Message;

interface MessageInterface
{
    public function getEvent();

    public function getData();

    public function getChannel();

    public function __toString();
}