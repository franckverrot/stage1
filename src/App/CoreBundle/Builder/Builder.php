<?php

namespace App\CoreBundle\Builder;

use App\CoreBundle\Entity\Build;
use App\CoreBundle\Entity\BuildScript;

use App\CoreBundle\Docker\AppContainer;
use App\CoreBundle\Docker\BuildContainer;
use App\CoreBundle\Docker\PrepareContainer;

use Symfony\Component\Process\Process;
use Symfony\Bridge\Doctrine\RegistryInterface;

use Docker\Docker;
use Docker\PortCollection;

use Psr\Log\LoggerInterface;

use Exception;
use RuntimeException;

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

        $project = $build->getProject();

        $logger->info('starting build', [
            'build' => $build->getId(),
            'project' => $project->getGithubFullName(),
            'project_id' => $project->getId(),
            'ref' => $build->getRef(),
            'timeout' => $timeout,
            'force_local_build_yml' => $build->getForceLocalBuildYml(),
        ]);

        /**
         * Generate build script using Yuhao
         */
        $logger->info('generating build script');

        $builder = $project->getDockerContextBuilder();
        $builder->from('yuhao');

        $docker->build($builder->getContext(), $build->getImageName('yuhao'), false, true, true);

        $prepareContainer = new PrepareContainer($build);

        if ($build->getForceLocalBuildYml()) {
            $prepareContainer->addEnv(['FORCE_LOCAL_BUILD_YML=1']);
        }

        $manager = $docker->getContainerManager();

        $manager
            ->run($prepareContainer)
            ->wait($prepareContainer);

        if ($prepareContainer->getExitCode() != 0) {
            $exitCode = $prepareContainer->getExitCode();
            $exitCodeLabel = Process::$exitCodes[$exitCode];

            $message = sprintf('failed to generate build scripts (exit code %d (%s))', $exitCode, $exitCodeLabel);

            $logger->error($message, [
                'build' => $build->getId(),
                'container' => $prepareContainer->getId(),
                'container_name' => $prepareContainer->getName(),
                'exit_code' => $exitCode,
                'exit_code_label' => $exitCodeLabel,
            ]);

            $docker->commit($prepareContainer, [
                'repo' => $build->getImageName('yuhao'),
                'tag' => 'failed'
            ]);

            throw new Exception($message, $prepareContainer->getExitCode());
        }

        // @todo remove yuhao container
        // $manager->remove($prepareContainer); => 406 ?!

        $logger->info('yuhao finished executing, retrieving build script', ['build' => $build->getId()]);

        $output = '';

        $manager->attach($prepareContainer, function($type, $chunk) use (&$output) {
            $output .= $chunk;
        }, true, false, false, true, true);

        $logger->info('got response from yuhao', [
            'build' => $build->getId(),
            'response' => $output,
            'parsed_response' => json_decode($output, true)
        ]);

        $script = BuildScript::fromJson($output);
        $script->setBuild($build);

        $em->persist($script);

        $options = $project->getDefaultBuildOptions();
        $options = $options->resolve($script->getNormalizedConfig());

        $build->setOptions($options);

        $logger->info('resolved options', ['options' => $options]);

        /**
         * Launch actual build
         */
        $logger->info('building base build container', ['build' => $build->getId(), 'image_name' => $build->getImageName()]);

        if (explode(':', $options['image'])[0] !== 'symfony2') {
            throw new RuntimeException(sprintf('Only the "symfony2" image is supported right now'));
        }

        $builder = $project->getDockerContextBuilder();
        $builder->add('/usr/local/bin/yuhao_build', $script->getBuildScript());
        $builder->add('/usr/local/bin/yuhao_run', $script->getRunScript());
        $builder->run('chmod -R +x /usr/local/bin/');
        $builder->from($options['image']);

        $response = $docker->build($builder->getContext(), $build->getImageName(), false, true, true);

        $buildContainer = new BuildContainer($build);
        $buildContainer->addEnv($options['env']);

        if ($build->getForceLocalBuildYml()) {
            $buildContainer->addEnv(['FORCE_LOCAL_BUILD_YML=1']);
        }

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

            $docker->commit($buildContainer, [
                'repo' => $build->getImageName(),
                'tag' => 'failed'
            ]);

            throw new Exception($message, $buildContainer->getExitCode());
        }

        /**
         * Build successful!
         */
        $logger->info('build successful, committing', ['build' => $build->getId(), 'container' => $buildContainer->getId()]);
        $docker->commit($buildContainer, ['repo' => $build->getImageName()]);

        $logger->info('removing build container', ['build' => $build->getId(), 'container' => $buildContainer->getId()]);
        $manager->remove($buildContainer);

        /**
         * Launch App container
         */
        $ports = new PortCollection(80, 22);

        $appContainer = new AppContainer($build);
        $appContainer->addEnv($build->getProject()->getContainerEnv());
        $appContainer->setExposedPorts($ports);

        if ($build->getForceLocalBuildYml()) {
            $appContainer->addEnv(['FORCE_LOCAL_BUILD_YML=1']);
        }

        $logger->info('running app container', ['build' => $build->getId(), 'container' => $appContainer->getId()]);
        $manager->run($appContainer, ['PortBindings' => $ports->toSpec()]);

        return $appContainer;
    }
}