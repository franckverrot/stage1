<?php

namespace App\CoreBundle\Message;

class BuildStepMessage extends AbstractMessage
{
    private $step;

    public function __construct(Build $build, $step)
    {
        $this->step = $step;

        parent::__construct($build);
    }

    public function toArray()
    {
        $build = $this->getBuild();
        $step = $this->step;

        return [
            'event' => 'build.step',
            'channel' => $build->getChannel(),
            'data' => [
                'build' => $build->asMessage(),
                'announce' => ['step' => $step],
            ]
        ];
    }
}