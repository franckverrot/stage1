<?php

namespace App\CoreBundle;

use App\CoreBundle\DependencyInjection\AppCoreExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class AppCoreBundle extends Bundle
{
    public function getContainerExtension()
    {
        return new AppCoreExtension();
    }
}
