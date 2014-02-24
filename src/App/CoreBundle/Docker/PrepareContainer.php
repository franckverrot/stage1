<?php

namespace App\CoreBundle\Docker;

use App\CoreBundle\Entity\Build;

use Docker\Container;

class PrepareContainer extends Container
{
    public function __construct(Build $build)
    {
        parent::__construct([
            'Image' => $build->getImageName('yuhao'),
            'Cmd' => ['yuhao', $build->getProject()->getGitUrl(), $build->getRef()],
        ]);
    }
}