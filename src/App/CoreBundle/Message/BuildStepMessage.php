<?php

namespace App\CoreBundle\Message;

use App\CoreBundle\Entity\Build;

class BuildStepMessage extends AbstractMessage
{
    private $step;

    public function __construct(Build $build, $step)
    {
        $this->step = $step;

        parent::__construct($build);
    }

    public function getData()
    {
        return array_merge(parent::getData(), [
            'announce' => ['step' => $step],
        ]);
    }
}