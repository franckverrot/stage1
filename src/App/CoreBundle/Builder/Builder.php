<?php

namespace App\CoreBundle\Builder;

use App\Model\Build;
use App\Model\BuildScript;

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
        'dummy_duration' => 10,
        'composer_enable_global_cache' => false,
        'composer_enable_project_cache' => false,
        'composer_cache_path' => '/usr/local/share/composer/cache/'
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
     * @param App\Model\Build $build
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
        $builder->from('stage1/yuhao');

        $docker->build($builder->getContext(), $build->getImageName('yuhao'), false, true, true);

        $prepareContainer = new PrepareContainer($build);

        if ($build->getForceLocalBuildYml()) {
            $prepareContainer->addEnv(['FORCE_LOCAL_BUILD_YML=1']);
        }

        $manager = $docker->getContainerManager();

        $manager
            ->create($prepareContainer)
            ->start($prepareContainer)
            ->wait($prepareContainer, $timeout);

        if ($prepareContainer->getExitCode() != 0) {
            $exitCode = $prepareContainer->getExitCode();

            if (isset(Process::$exitCodes[$exitCode])) {
                $exitCodeLabel = Process::$exitCodes[$exitCode];
            } else {
                $exitCodeLabel = '';
            }

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

        $manager->attach($prepareContainer, true, false, false, true, true)->readAttach(function($type, $chunk) use (&$output) {
            $output .= $chunk;
        });

        $logger->info('got response from yuhao', [
            'build' => $build->getId(),
            'response' => $output,
            'parsed_response' => json_decode($output, true)
        ]);

        $script = BuildScript::fromJson($output);
        $script->setBuild($build);

        $em->persist($script);

        $options = $project->getDefaultBuildOptions();
        $options = $options->resolve($script->getConfig());

        $build->setOptions($options);

        $logger->info('resolved options', ['options' => $options]);

        /**
         * Launch actual build
         */
        $logger->info('building base build container', [
            'build' => $build->getId(),
            'image_name' => $build->getImageName()
        ]);

        $baseImage = strpos($options['image'], 'stage1/') !== 0
            ? 'stage1/'.$options['image']
            : $options['image'];

        $builder = $project->getDockerContextBuilder();
        $builder->add('/usr/local/bin/yuhao_build', $script->getBuildScript());
        $builder->add('/usr/local/bin/yuhao_run', $script->getRunScript());
        $builder->run('chmod -R +x /usr/local/bin/');
        $builder->from($baseImage);

        $response = $docker->build($builder->getContext(), $build->getImageName(), false, true, true);

        $buildContainer = new BuildContainer($build);
        $buildContainer->addEnv($options['env']);

        $script->setRuntimeEnv($buildContainer->getEnv());

        if ($build->getForceLocalBuildYml()) {
            $buildContainer->addEnv(['FORCE_LOCAL_BUILD_YML=1']);
        }

        $manager = $this->docker->getContainerManager();

        $logger->info('starting actual build', [
            'build' => $build->getId(),
            'timeout' => $timeout,
        ]);

        $hostConfig = [];

        if ($this->getOption('composer_enable_global_cache')) {
            $logger->info('enabling composer global cache', ['build' => $build->getId()]);
            $hostConfig['Binds'] = [$this->getOption('composer_cache_path').'/global:/.composer/cache'];
        } elseif ($this->getOption('composer_enable_project_cache')) {
            $cachePath = $this->getOption('composer_cache_path').'/'.$project->getGithubFullName();
            $logger->info('enabling composer project cache', ['build' => $build->getId(), 'project' => $project->getGithubFullName(), 'cache_path' => $cachePath]);

            if (!is_dir($cachePath)) {
                mkdir($cachePath, 0777, true);
            }

            $hostConfig['Binds'] = [realpath($cachePath).':/.composer/cache'];

        }

        $manager->create($buildContainer);

        $build->setContainer($buildContainer);

        $em->persist($build);
        $em->flush();
        
        $manager->start($buildContainer, $hostConfig);
        $manager->wait($buildContainer, $timeout);

        if ($buildContainer->getExitCode() !== 0) {
            $exitCode = $buildContainer->getExitCode();
            $exitCodeLabel = isset(Process::$exitCodes[$exitCode]) ? Process::$exitCodes[$exitCode] : '';

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
         * 
         * @todo the commit can timeout for no obvious reason, while actually committing
         *       catch the timeout and check if the image has been commited
         *          if yes, proceed
         *          if not, retry (3 times ?)
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

        $manager
            ->create($appContainer)
            ->start($appContainer, ['PortBindings' => $ports->toSpec()]);

        $logger->info('running app container', ['build' => $build->getId(), 'container' => $appContainer->getId()]);

        return $appContainer;
    }
}