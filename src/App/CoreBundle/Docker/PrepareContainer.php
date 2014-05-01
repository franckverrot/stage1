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
            'Cmd' => ['yuhao.sh'],
            'Env' => [
                'SSH_URL='.$build->getProject()->getGitUrl(),
                'REF='.$build->getRef(),
                'IS_PULL_REQUEST='.($build->isPullRequest() ? 1 : 0)
            ]
        ]);
    }
}