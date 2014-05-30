<?php

namespace App\CoreBundle\EventListener\Build;

use App\Model\Build;
use App\CoreBundle\Event\BuildFinishedEvent;
use Docker\Docker;
use Docker\Exception\ContainerNotFoundException;
use OldSound\RabbitMqBundle\RabbitMq\Producer;
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
     * @var OldSound\RabbitMqBundle\RabbitMq\Producer
     */
    private $stopProducer;

    /**
     * @param Psr\Log\LoggerInterface
     * @param Symfony\Bridge\Doctrine\RegistryInterface
     * @param Docker\Docker
     */
    public function __construct(LoggerInterface $logger, RegistryInterface $doctrine, Docker $docker, Producer $stopProducer)
    {
        $this->logger = $logger;
        $this->doctrine = $doctrine;
        $this->docker = $docker;
        $this->stopProducer = $stopProducer;

        $logger->info('initialized '.__CLASS__);
    }

    public function onBuildFinished(BuildFinishedEvent $event)
    {
        $build = $event->getBuild();

        if (!$build->isRunning()) {
            return;
        }

        $logger = $this->logger;
        $em = $this->doctrine->getManager();

        $buildRepository = $em->getRepository('Model:Build');
        $previousBuild = $buildRepository->findPreviousBuild($build);

        if (!$previousBuild) {
            $logger->info('no previous build', ['build' => $build->getId()]);
            
            return;
        }

        if (!$previousBuild->hasContainer()) {
            $logger->info('previous build does not have a container', [
                'build' => $build->getId(),
                'previous_build' => $previousBuild->getId()
            ]);

            $previousBuild->setStatus(Build::STATUS_OBSOLETE);
            $em->persist($previousBuild);

            return;
        }

        $logger->info('sending stop order', [
            'build' => $previousBuild->getId(),
            'routing_key' => $build->getRoutingKey(),
        ]);

        $this->stopProducer->publish(json_encode([
            'build_id' => $previousBuild->getId(),
            'status' => Build::STATUS_OBSOLETE,
        ]), $build->getRoutingKey());
    }
}