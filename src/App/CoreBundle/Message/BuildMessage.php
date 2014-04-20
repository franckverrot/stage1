<?php

namespace App\CoreBundle\Message;

use App\Model\Build;

class BuildMessage extends AbstractMessage
{
    public function __construct(Build $build, $message)
    {
        $this->setExtra([
            'message' => $message,
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