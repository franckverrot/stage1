<?php

namespace App\CoreBundle\Message;

use App\CoreBundle\Entity\Build;

class BuildStartedMessage extends AbstractMessage
{
    public function __construct(Build $build)
    {
        parent::__construct($build);
    }

    public function getRoutes()
    {
        $build = $this->getObject();

        return ['build' => [
            'show_url' => $this->generateUrl('app_core_build_show', ['id' => $build->getId()]),
            'kill_url' => $this->generateUrl('app_core_build_kill', ['id' => $build->getId()])
        ]];
    }
}