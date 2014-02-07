<?php

namespace App\CoreBundle;

use App\CoreBundle\Entity\Branch;
use App\CoreBundle\Entity\Build;
use App\CoreBundle\Entity\Project;
use App\CoreBundle\Entity\User;
use App\CoreBundle\Message\MessageFactory;
use OldSound\RabbitMqBundle\RabbitMq\Producer;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * App\CoreBundle\BuildScheduler
 */
class BuildScheduler
{
    private $doctrine;

    private $buildProducer;

    private $websocketProducer;

    private $messageFactory;

    public function __construct(LoggerInterface $logger, RegistryInterface $doctrine, Producer $buildProducer, Producer $websocketProducer, MessageFactory $messageFactory)
    {
        $this->logger = $logger;
        $this->doctrine = $doctrine;
        $this->buildProducer = $buildProducer;
        $this->websocketProducer = $websocketProducer;
        $this->messageFactory = $messageFactory;
    }

    /**
     * Schedules a build
     * 
     * @see App\CoreBundle\EventListener\BuildBranchRelationSubscriber for automatic creation of non-existing branches
     */
    public function schedule(Project $project, $ref, $hash, User $initiator = null)
    {
        $this->logger->info('scheduling build', [
            'project' => $project->getId(),
            'ref' => $ref,
            'hash' => $hash,
        ]);

        $em = $this->doctrine->getManager();

        $build = new Build();
        $build->setProject($project);
        $build->setStatus(Build::STATUS_SCHEDULED);
        $build->setRef($ref);
        $build->setHash($hash);

        if (null !== $initiator) {
            $build->setInitiator($initiator);
        }

        $em->persist($build);
        $em->flush();

        $this->buildProducer->publish(json_encode([
            'build_id' => $build->getId()
        ]));

        $message = $this->messageFactory->createBuildScheduled($build);
        $this->websocketProducer->publish($message);

        return $build;
    }
}