<?php

namespace App\CoreBundle\Message;

class BuildStartedMessage extends AbstractMessage
{
    public function toArray()
    {
        $build = $this->getBuild();

        return [
            'event' => 'build.started',
            'channel' => $build->getChannel(),
            'timestamp' => microtime(true),
            'data' => [
                'progress' => 0,
                'build' => $build->asMessage(),
            ]
        ];
    }
}