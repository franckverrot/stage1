<?php

namespace App\CoreBundle\Message;

use App\CoreBundle\Entity\BuildLog;

class BuildLogMessage extends AbstractMessage
{
    public function __construct(BuildLog $buildLog)
    {
        parent::__construct($buildLog);
    }
}