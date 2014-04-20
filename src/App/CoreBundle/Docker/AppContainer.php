<?php

namespace App\CoreBundle\Docker;

use App\Model\Build;

use Docker\Container;

class AppContainer extends Container
{
    public function __construct(Build $build, $cmd)
    {
        parent::__construct([
            'Memory' => 256 * 1024 * 1204,  // @todo use configuration, maybe get from project
            'Cmd' => $cmd,
            'Image' => $build->getImageName(),
            'Env' => [
                'SYMFONY_ENV=prod',
            ]
        ]);
    }
}