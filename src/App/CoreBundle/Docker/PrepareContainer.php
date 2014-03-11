<?php

namespace App\CoreBundle\Docker;

use App\Model\Build;

use Docker\Container;

class PrepareContainer extends Container
{
    public function __construct(Build $build)
    {
        parent::__construct([
            'Image' => $build->getImageName('yuhao'),
            'Cmd' => ['yuhao.sh', $build->getProject()->getGitUrl(), $build->getRef()],
        ]);
    }
}