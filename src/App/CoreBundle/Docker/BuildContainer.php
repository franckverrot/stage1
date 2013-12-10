<?php

namespace App\CoreBundle\Docker;

use App\CoreBundle\Entity\Build;

use Docker\Container;

class BuildContainer extends Container
{
    public function __construct(Build $build)
    {
        parent::__construct([
            'Env' => [
                'BUILD_ID='.$build->getId(),
                'PROJECT_ID='.$build->getProject()->getId(),
                'CHANNEL='.$build->getChannel(),
                'SSH_URL='.$build->getProject()->getSshUrl(),
                'REF='.$build->getRef(),
                'HASH='.$build->getHash(),
                /**
                 * @todo there must be a way to avoid requiring a valid access token
                 *       I think the token is only used to avoid hitting github's
                 *       API limit through composer, so maybe there's a way to use a
                 *       stage1 specific token instead
                 */
                'ACCESS_TOKEN='.$build->getProject()->getUsers()->first()->getAccessToken(),
                'SYMFONY_ENV=prod',
            ],
            'Image' => $build->getImageName(),
            'Cmd' => ['buildapp']
        ]);
    }
}