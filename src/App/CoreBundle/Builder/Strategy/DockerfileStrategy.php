<?php

namespace App\CoreBundle\Builder\Strategy;

use App\Model\Build;
use App\Model\BuildScript;
use Docker\Docker;
use Doctrine\Common\Persistence\ObjectManager;
use Psr\Log\LoggerInterface;

class DockerfileStrategy
{
    private $logger;

    private $docker;

    private $objectManager;

    private $options = [];

    public function __construct(LoggerInterface $logger, Docker $docker, ObjectManager $objectManager, array $options)
    {
        $this->logger = $logger;
        $this->docker = $docker;
        $this->objectManager = $objectManager;
        $this->options = $options;
    }

    public function build(Build $build, BuildScript $script)
    {

    }
}