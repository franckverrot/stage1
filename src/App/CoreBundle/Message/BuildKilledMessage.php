<?php

namespace App\CoreBundle\Message;

use App\CoreBundle\Entity\Build;

class BuildKilledMessage extends AbstractMessage
{
    public function __construct(Build $build)
    {
        parent::__construct($build);
    }

    public function getRoutes()
    {
        $build = $this->getObject();

        return [
            'build_url' => $this->generateUrl('app_core_build_show', ['id' => $build->getId()]),
            'schedule_url' => $this->generateUrl('app_core_project_schedule_build', ['id' => $build->getId()]),
        ];
    }
}