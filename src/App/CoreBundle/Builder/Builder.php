<?php

namespace App\CoreBundle\Builder;

use App\CoreBundle\Entity\Build;
use App\CoreBundle\Docker\AppContainer;
use App\CoreBundle\Docker\BuildContainer;

use Docker\Docker;
use Docker\Context;
use Docker\PortCollection;

use Psr\Log\LoggerInterface;
use Redis;

use Exception;

class Builder
{
    /**
     * @var Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var Docker\Docker
     */
    private $docker;
    
    /**
     * @param Psr\Log\LoggerInterface $logger
     * @param Docker\Docker $docker
     */
    public function __construct(LoggerInterface $logger, Docker $docker)
    {
        $this->docker = $docker;
        $this->logger = $logger;
    }

    /**
     * @param App\CoreBundle\Entity\Build $build
     * 
     * @return Docker\Container
     */
    public function run(Build $build)
    {
        $logger = $this->logger;
        $docker = $this->docker;

        $logger->info('starting build #'.$build->getId());

        // @todo the base container can (and should?) be built during project import
        //       that's one lest step during the build
        $context = new Context();
        $context->from('symfony2:latest');
        $context->add('/root/.ssh/id_rsa', $build->getProject()->getPrivateKey());
        $context->add('/root/.ssh/id_rsa.pub', $build->getProject()->getPublicKey());
        $context->add('/root/.ssh/config', <<<SSH
Host github.com
    Hostname github.com
    User git
    IdentityFile /root/.ssh/id_rsa
    StrictHostKeyChecking no
SSH
);
        $context->run('chmod -R 0600 /root/.ssh');
        $context->run('chown -R root:root /root/.ssh');

        $docker->build($context, $build->getImageName());

        $buildContainer = new BuildContainer($build);

        $manager = $this->docker->getContainerManager();
        $manager->run($buildContainer);
        $manager->wait($buildContainer);

        if ($buildContainer->getExitCode() !== 0) {
            $message = 'Build container stopped with exit code '.$buildContainer->getExitCode();
            $logger->error($message);
            throw new Exception($message, $buildContainer->getExitCode());
        }

        $docker->commit($buildContainer, ['repo' => $build->getImageName()]);

        $appContainer = new AppContainer($build);
        $ports = new PortCollection(80, 22);

        $manager->run($appContainer, ['PortBindings' => $ports->toSpec()]);

        return $appContainer;
    }
}