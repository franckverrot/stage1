<?php

namespace App\CoreBundle\Message;

use App\CoreBundle\Entity\Build;

class BuildStartedMessage extends AbstractMessage
{
    private $build;

    public function __construct(Build $build)
    {
        $this->build = $build;
    }

    public function __toString()
    {
        return json_encode([
            'event' => 'build.started',
            'channel' => $this->build->getChannel(),
            'timestamp' => microtime(true),
            'data' => [
                'progress' => 0,
                'build' => $this->build->asMessage(),
            ]
        ])
    }
}