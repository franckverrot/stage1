<?php

namespace App\CoreBundle\Message;

use App\CoreBundle\Entity\Build;

class BuildStepMessage extends AbstractMessage
{
    public function __construct(Build $build, $step)
    {
        $this->setExtra(['announce' => ['step' => $step]]);

        parent::__construct($build);
    }
}