<?php

namespace App\CoreBundle\Docker;

use App\CoreBundle\Entity\Build;

use Docker\Container;

class AppContainer extends Container
{
    public function __construct(Build $build)
    {
        parent::__construct([
            'Memory' => 128 * 1024 * 1204,  // @todo use configuration, maybe get from project
            'Cmd' => ['runapp'],
            'Image' => $build->getImageName(),
        ]);
    }
}