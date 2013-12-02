<?php

namespace App\CoreBundle\Event;

use App\CoreBundle\Entity\Build;

use Symfony\Component\EventDispatcher\Event;

class BuildStartedEvent extends AbstractBuildEvent
{
}