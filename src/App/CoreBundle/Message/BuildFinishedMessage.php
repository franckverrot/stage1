<?php

namespace App\CoreBundle\Message;

class BuildFinishedMessage extends AbstractMessage
{
    public function toArray()
    {
        $build = $this->getBuild();
        
        return [
            'event' => 'build.finished',
            'channel' => $build->getChannel(),
            'timestamp' => microtime(true),
            'data' => [
                'progress' => 0,
                'build' => $build->asMessage(),
            ]
        ];
    }
}