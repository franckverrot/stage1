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

    public function __toString()
    {
        $build = $this->build;
        $step = $this->step;

        return json_encode([
            'event' => 'build.step',
            'channel' => $build->getChannel(),
            'data' => [
                'build' => $build->asMessage(),
                'announce' => ['step' => $step],
            ]
        ]);
    }
}