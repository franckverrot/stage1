<?php

namespace App\CoreBundle\Builder;

use App\CoreBundle\Entity\Build;
use App\CoreBundle\Docker\AppContainer;
use App\CoreBundle\Docker\BuildContainer;

use Symfony\Component\Process\Process;

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

        $logger->info('starting build', ['build' => $build->getId()]);

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

        $logger->info('building base build container', ['build' => $build->getId()]);
        $docker->build($context, $build->getImageName());

        $buildContainer = new BuildContainer($build);
        $buildContainer->addEnv($build->getProject()->getContainerEnv());

        $manager = $this->docker->getContainerManager();

        $logger->info('starting actual build', ['build' => $build->getId()]);

        $manager->run($buildContainer);
        $manager->wait($buildContainer);

        $build->setContainer($buildContainer);
        
        if ($buildContainer->getExitCode() !== 0) {
            $exitCode = $buildContainer->getExitCode();
            $exitCodeLabel = Process::$exitCodes[$exitCode];

            $message = sprintf('build container stopped with exit code %d (%s)', $exitCode, $exitCodeLabel);

            $logger->error($message, [
                'build' => $build->getId(),
                'container' => $buildContainer->getId(),
                'exit_code' => $exitCode,
                'exit_code_label' => $exitCodeLabel,
            ]);

            $docker->commit($buildContainer, ['repo' => $build->getImageName(), 'tag' => 'failed']);

            throw new Exception($message, $buildContainer->getExitCode());
        }

        $logger->info('build successful, committing', ['build' => $build->getId()]);

        $docker->commit($buildContainer, ['repo' => $build->getImageName()]);

        $ports = new PortCollection(80, 22);

        $appContainer = new AppContainer($build);
        $appContainer->addEnv($build->getProject()->getContainerEnv());
        $appContainer->setExposedPorts($ports);

        $logger->info('running app container', ['build' => $build->getId()]);
        $manager->run($appContainer, ['PortBindings' => $ports->toSpec()]);

        return $appContainer;
    }
}