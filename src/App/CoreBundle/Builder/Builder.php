<?php

namespace App\CoreBundle\Builder;

use App\CoreBundle\Entity\Build;
use App\CoreBundle\Docker\AppContainer;
use App\CoreBundle\Docker\BuildContainer;

use Symfony\Component\Process\Process;
use Symfony\Bridge\Doctrine\RegistryInterface;

use Docker\Docker;
use Docker\Context\Context;
use Docker\Context\ContextBuilder;
use Docker\PortCollection;

use Psr\Log\LoggerInterface;

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
     * @var Symfony\Bridge\Doctrine\RegistryInterface
     */
    private $doctrine;

    /**
     * @var array
     */
    private $options = [
        'dummy' => false,
        'dummy_duration' => 10
    ];
    
    /**
     * @param Psr\Log\LoggerInterface $logger
     * @param Docker\Docker $docker
     * @param Symfony\Bridge\Doctrine\RegistryInterface $doctrine
     */
    public function __construct(LoggerInterface $logger, Docker $docker, RegistryInterface $doctrine)
    {
        $this->docker = $docker;
        $this->logger = $logger;
        $this->doctrine = $doctrine;
    }

    /**
     * @param string $name
     * @param mixed  $value
     * 
     * @return App\CoreBundle\Builder\Builder
     */
    public function setOption($name, $value)
    {
        $this->options[$name] = $value;
    }

    /**
     * @param string $name
     * 
     * @return mixed
     */
    public function getOption($name)
    {
        return $this->options[$name];
    }

    /**
     * @param App\CoreBundle\Entity\Build $build
     * 
     * @return Docker\Container
     */
    public function run(Build $build, $timeout = null)
    {
        $logger = $this->logger;
        $docker = $this->docker;
        $em = $this->doctrine->getManager();

        if ($this->getOption('dummy')) {
            $logger->info('dummy build, sleeping', ['duration' => $this->getOption('dummy_duration')]);
            sleep($this->getOption('dummy_duration'));

            $build->setPort(42);

            return true;
        }

        $logger->info('starting build', [
            'build' => $build->getId(),
            'project' => $build->getProject()->getGithubFullName(),
            'project_id' => $build->getProject()->getId(),
            'ref' => $build->getRef(),
            'timeout' => $timeout
        ]);

        $env  = 'PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"'.PHP_EOL;
        $env .= 'SYMFONY_ENV=prod'.PHP_EOL;
        $env .= $build->getProject()->getEnv();

        // @todo the base container can (and should?) be built during project import
        //       that's one lest step during the build
        // @todo also, move that to a BuildContext
        $builder = new ContextBuilder();
        $builder->setFormat(Context::FORMAT_TAR);
        $builder->from($build->getBaseImageName());
        $builder->add('/etc/environment', $env);
        $builder->add('/root/.ssh/id_rsa', $build->getProject()->getPrivateKey());
        $builder->add('/root/.ssh/id_rsa.pub', $build->getProject()->getPublicKey());
        $builder->add('/root/.ssh/config', <<<SSH
Host github.com
    Hostname github.com
    User git
    IdentityFile /root/.ssh/id_rsa
    StrictHostKeyChecking no
SSH
);
        $builder->run('chmod -R 0600 /root/.ssh');
        $builder->run('chown -R root:root /root/.ssh');

        $logger->info('building base build container', ['build' => $build->getId()]);
        $docker->build($builder->getContext(), $build->getImageName(), false, true, true);

        $buildContainer = new BuildContainer($build);
        $buildContainer->addEnv($build->getProject()->getContainerEnv());

        $manager = $this->docker->getContainerManager();

        $logger->info('starting actual build', [
            'build' => $build->getId(),
            'timeout' => $timeout,
        ]);

        $manager->create($buildContainer);

        $build->setContainer($buildContainer);

        $em->persist($build);
        $em->flush();
        
        $manager->start($buildContainer);
        $manager->wait($buildContainer, $timeout);

        if ($buildContainer->getExitCode() !== 0) {
            $exitCode = $buildContainer->getExitCode();
            $exitCodeLabel = Process::$exitCodes[$exitCode];

            $message = sprintf('build container stopped with exit code %d (%s)', $exitCode, $exitCodeLabel);

            $logger->error($message, [
                'build' => $build->getId(),
                'container' => $buildContainer->getId(),
                'container_name' => $buildContainer->getName(),
                'exit_code' => $exitCode,
                'exit_code_label' => $exitCodeLabel,
            ]);

            $docker->commit($buildContainer, ['repo' => $build->getImageName(), 'tag' => 'failed']);

            throw new Exception($message, $buildContainer->getExitCode());
        }

        $logger->info('build successful, committing', ['build' => $build->getId(), 'container' => $buildContainer->getId()]);
        $docker->commit($buildContainer, ['repo' => $build->getImageName()]);

        $logger->info('removing build container', ['build' => $build->getId(), 'container' => $buildContainer->getId()]);
        $manager->remove($buildContainer);

        $ports = new PortCollection(80, 22);

        $appContainer = new AppContainer($build);
        $appContainer->addEnv($build->getProject()->getContainerEnv());
        $appContainer->setExposedPorts($ports);

        $logger->info('running app container', ['build' => $build->getId(), 'container' => $appContainer->getId()]);
        $manager->run($appContainer, ['PortBindings' => $ports->toSpec()]);

        return $appContainer;
    }
}