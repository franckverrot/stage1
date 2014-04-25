<?php

namespace App\CoreBundle\Docker;

use App\Model\Build;

use Docker\Container;

class AppContainer extends Container
{
    public function __construct(Build $build, $cmd = null)
    {
        $config = [
            'Memory' => 256 * 1024 * 1204,  // @todo use configuration, maybe get from project
            'Image' => $build->getImageName(),
            'Env' => [
                'SYMFONY_ENV=prod',
            ]
        ];

        if (null !== $cmd) {
            $config['Cmd'] = $cmd;
        }

        parent::__construct($config);
    }
}