<?php

namespace App\CoreBundle\Message;

use App\Model\BuildLog;

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
        return [
            'buildLog' => $this->getObject()->asMessage(),
            'build' => $this->getObject()->getBuild()->asMessage()
        ];
    }
}