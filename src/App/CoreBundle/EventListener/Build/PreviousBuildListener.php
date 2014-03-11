<?php

namespace App\CoreBundle\EventListener\Build;

use App\Model\Build;
use App\CoreBundle\Event\BuildFinishedEvent;

use Docker\Docker;
use Docker\Exception\ContainerNotFoundException;

use Psr\Log\LoggerInterface;
use Symfony\Bridge\Doctrine\RegistryInterface;

use Exception;

/**
 * Marks a previous build for a same ref obsolete
 */
class PreviousBuildListener
{
    /**
     * @var Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var Symfony\Bridge\Doctrine\RegistryInterface
     */
    private $doctrine;

    /**
     * @var Docker\Docker
     */
    private $docker;

    /**
     * @param Psr\Log\LoggerInterface
     * @param Symfony\Bridge\Doctrine\RegistryInterface
     * @param Docker\Docker
     */
    public function __construct(LoggerInterface $logger, RegistryInterface $doctrine, Docker $docker)
    {
        $this->logger = $logger;
        $this->doctrine = $doctrine;
        $this->docker = $docker;

        $logger->info('initialized '.__CLASS__);
    }

    public function onBuildFinished(BuildFinishedEvent $event)
    {
        $build = $event->getBuild();

        if (!$build->isRunning()) {
            return;
        }

        $em = $this->doctrine->getManager();

        $buildRepository = $em->getRepository('Model:Build');
        $previousBuild = $buildRepository->findPreviousBuild($build);

        if (!$previousBuild) {
            $this->logger->info('no previous build', ['build' => $build->getId()]);
            
            return;
        }

        if (!$previousBuild->hasContainer()) {
            $this->logger->info('previous build does not have a container', [
                'build' => $build->getId(),
                'previous_build' => $previousBuild->getId()
            ]);

            return;
        }

        try {
            $manager = $this->docker->getContainerManager();
            $container = $previousBuild->getContainer();

            $manager
                ->stop($container)
                ->remove($container);

            $this->logger->info('stopped and removed previous container', [
                'build' => $build->getId(),
                'previous_build' => $previousBuild->getId(),
                'previous_container' => $previousBuild->getContainer()->getId()
            ]);
        } catch (ContainerNotFoundException $e) {
            $this->logger->warn('found previous container but docker did not find it', [
                'build' => $build->getId(),
                'previous_build' => $previousBuild->getId(),
                'previous_container' => $previousBuild->getContainer()->getId()
            ]);
        } catch (Exception $e) {
            $this->logger->error('error stopping previous container', [
                'build' => $build->getId(),
                'previous_build' => $previousBuild->getId(),
                'previous_container' => $previousBuild->getContainer()->getId(),
                'message' => $e->getMessage(),
            ]);
        }

        $previousBuild->setStatus(Build::STATUS_OBSOLETE);

        $em->persist($previousBuild);
        $em->flush();
    }
}