<?php

namespace App\CoreBundle;

use App\Model\Branch;
use App\Model\Build;
use App\Model\Project;
use App\Model\User;
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

    private $killProducer;

    private $websocketProducer;

    private $messageFactory;

    public function __construct(LoggerInterface $logger, RegistryInterface $doctrine, Producer $buildProducer, Producer $killProducer, Producer $websocketProducer, MessageFactory $messageFactory)
    {
        $this->logger = $logger;
        $this->doctrine = $doctrine;
        $this->buildProducer = $buildProducer;
        $this->killProducer = $killProducer;
        $this->websocketProducer = $websocketProducer;
        $this->messageFactory = $messageFactory;
    }

    /**
     * Schedules a build
     * 
     * @see App\CoreBundle\EventListener\BuildBranchRelationSubscriber for automatic creation of non-existing branches
     */
    public function schedule(Project $project, $ref, $hash, User $initiator = null, $options = [])
    {
        $logger = $this->logger;
        $logger->info('scheduling build', ['project' => $project->getId(), 'ref' => $ref, 'hash' => $hash]);

        $em = $this->doctrine->getManager();

        // @todo I guess this should be in a build.scheduled event listener
        $alreadyRunningBuilds = $em->getRepository('Model:Build')->findPendingByRef($project, $ref);

        foreach ($alreadyRunningBuilds as $build) {
            // @todo instead of retrieving then updating builds to be canceled, directly issue an UPDATE
            //       it should avoid most race conditions
            if ($build->isScheduled()) {
                $logger->info('canceling same ref build', ['ref' => $ref, 'canceled_build' => $build->getId()]);
                $build->setStatus(Build::STATUS_CANCELED);
                $em->persist($build);
                $em->flush();
            } else {
                $logger->info('killing same ref build', ['ref' => $ref, 'canceled_build' => $build->getId()]);
                $this->killProducer->publish(json_encode(['build_id' => $build->getId()]));
            }
        }

        $build = new Build();
        $build->setProject($project);
        $build->setStatus(Build::STATUS_SCHEDULED);
        $build->setRef($ref);
        $build->setHash($hash);

        if (null !== $initiator) {
            $build->setInitiator($initiator);
        }

        if (isset($options['force_local_build_yml']) && $options['force_local_build_yml']) {
            $build->setForceLocalBuildYml(true);
        }

        /**
         * @todo move this outside, it belongs in a controller
         *       this will allow to remove the $options argument
         */
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