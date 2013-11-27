<?php

namespace App\CoreBundle\Message;

use App\CoreBundle\Entity\BuildLog;

class BuildLogMessage extends AbstractMessage
{
    public function __construct(BuildLog $buildLog)
    {
        parent::__construct($buildLog);
    }

    public function getChannel()
    {
        return $this->getObject()->getBuild()->getChannel();
    }

    public function getData()
    {
        return ['build' => $this->getObject()->getBuild()->asMessage()];
    }
}