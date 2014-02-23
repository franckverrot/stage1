<?php

namespace App\CoreBundle\Docker;

use Docker\Container;

class PrepareContainer extends Container
{
    public function __construct()
    {
        parent::__construct([
            'Image' => 'yuhao',
            'Cmd' => 'yuhao'
        ]);
    }
}