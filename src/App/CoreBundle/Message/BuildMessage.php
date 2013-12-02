<?php

namespace App\CoreBundle\Message;

use App\CoreBundle\Entity\Build;

class BuildMessage extends AbstractMessage
{
    public function __construct(Build $build, $message)
    {
        $this->setExtra([
            'content' => $message,
            'lenght' => strlen($message),
            'type' => 'output',
            'stream' => 1,
        ]);

        parent::__construct($build);
    }

    public function getEvent()
    {
        return 'build.log';
    }
}