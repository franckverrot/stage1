<?php

namespace App\CoreBundle\Consumer;

use App\Model\Build;
use App\Model\Project;
use Docker\Docker;
use Docker\Exception\ContainerNotFoundException;
use Exception;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Doctrine\RegistryInterface;


class StopConsumer implements ConsumerInterface
{
    private $logger;

    private $docker;

    private $doctrine;

    public function __construct(LoggerInterface $logger, Docker $docker, RegistryInterface $doctrine)
    {
        $this->logger = $logger;
        $this->docker = $docker;
        $this->doctrine = $doctrine;

        $logger->info('initialized '.__CLASS__);
    }

    public function execute(AMQPMessage $message)
    {
        $logger = $this->logger;
        $body = json_decode($message->body);

        $logger->info('received stop order', ['build' => $body->build_id]);

        $buildRepo = $this->doctrine->getRepository('Model:Build');
        $build = $buildRepo->find($body->build_id);

        if (!$build) {
            $logger->warn('could not find build', ['build' => $body->build_id]);

            return;
        }

        if (!$container = $build->getContainer()) {
            $logger->info('build has no container');

            return;
        }

        try {

            $this
                ->docker
                ->getContainerManager()
                ->stop($container)
                ->remove($container);

            $this->logger->info('stopped and removed container', [
                'build' => $build->getId(),
                'container' => $build->getContainer()->getId()
            ]);
        } catch (ContainerNotFoundException $e) {
            $this->logger->warn('found container but docker did not find it', [
                'build' => $build->getId(),
                'container' => $build->getContainer()->getId()
            ]);
        } catch (Exception $e) {
            $this->logger->error('error stopping container', [
                'build' => $build->getId(),
                'container' => $build->getContainer()->getId(),
                'message' => $e->getMessage(),
            ]);
        }

        $build->setStatus($body->status ?: Build::STATUS_STOPPED);

        $em = $this->doctrine->getManager();
        $em->persist($build);
        $em->flush();
    }
}