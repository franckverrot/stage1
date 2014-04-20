<?php

namespace App\CoreBundle\Builder;

use App\Model\Build;
use App\Model\BuildScript;

use App\CoreBundle\Docker\AppContainer;
use App\CoreBundle\Docker\BuildContainer;
use App\CoreBundle\Docker\PrepareContainer;
use App\CoreBundle\Builder\Strategy\DockerfileStrategy;
use App\CoreBundle\Builder\Strategy\DefaultStrategy;

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

        $strategy = array_key_exists('path', $options['dockerfile'])
            ? new DockerfileStrategy($logger, $docker, $em, $this->options)
            : new DefaultStrategy($logger, $docker, $em, $this->options);

        $logger->info('elected strategy', [
            'strategy' => get_class($strategy),
        ]);

        return $strategy->build($build, $script);
    }
}