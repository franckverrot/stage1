<?php

namespace App\CoreBundle\Consumer;

use App\CoreBundle\Entity\Build;
use App\CoreBundle\Entity\Project;
use App\CoreBundle\Message\MessageFactory;

use Docker\Docker;

use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use OldSound\RabbitMqBundle\RabbitMq\Producer;
use PhpAmqpLib\Message\AMQPMessage;

use Psr\Log\LoggerInterface;

use Symfony\Component\Process\ProcessBuilder;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Bundle\FrameworkBundle\Routing\Router;

use RuntimeException;
use InvalidArgumentException;
use Exception;

class KillConsumer implements ConsumerInterface
{
    private $logger;

    private $docker;

    private $doctrine;

    private $factory;

    private $producer;

    private $router;

    private $timeout = 10;

    public function __construct(LoggerInterface $logger, Docker $docker, RegistryInterface $doctrine, MessageFactory $factory, Producer $producer, Router $router)
    {
        $this->logger = $logger;
        $this->docker = $docker;
        $this->doctrine = $doctrine;
        $this->factory = $factory;
        $this->producer = $producer;
        $this->router = $router;

        $logger->info('initialized '.__CLASS__);
    }

    public function generateUrl($route, $parameters = array(), $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH)
    {
        return $this->router->generate($route, $parameters, $referenceType);
    }

    public function getDoctrine()
    {
        return $this->doctrine;
    }

    private function persistAndFlush($entity)
    {
        $em = $this->getDoctrine()->getManager();
        $em->persist($entity);
        $em->flush();
    }

    public function getPendingBuildsCount(Project $project)
    {
        $buildRepo = $this->doctrine->getRepository('AppCoreBundle:Build');
        $qb = $buildRepo->createQueryBuilder('b');

        $query = $buildRepo->createQueryBuilder('b')
           ->select('count(b.id)')
            ->where('b.project = ?1')
            ->andWhere('b.status IN (?2)')
            ->setParameters([
                1 => $project->getId(),
                2 => [Build::STATUS_BUILDING, Build::STATUS_SCHEDULED]
            ])
            ->getQuery();

        try {
            return (int) $query->getSingleScalarResult();
        } catch (Exception $e) {
            return 0;
        }
    }

    public function execute(AMQPMessage $message)
    {
        $logger = $this->logger;
        $body = json_decode($message->body);

        $logger->info('received kill order', ['build' => $body->build_id]);

        $buildRepo = $this->doctrine->getRepository('AppCoreBundle:Build');
        $build = $buildRepo->find($body->build_id);

        if (!$build) {
            $logger->warn('could not find build', ['build' => $body->build_id]);
            return;
        }

        if (!$build->getPid()) {
            $logger->warn('build has no pid', ['build' => $build->getId()]);
            return;
        }

        $logger->info('build found, sending SIGTERM', ['build' => $build->getId()]);

        $terminated = true;

        if (posix_kill($build->getPid(), SIGTERM)) {

            $terminated = false;

            for ($i = 0; $i <= $this->timeout; $i++) {
                if (false === posix_kill($build->getPid(), 0)) {
                    $terminated = true;
                    break;
                }

                $logger->info('build still alive...', ['build' => $build->getId()]);

                sleep(1);
            }            
        }

        if (!$terminated) {
            $logger->info('sending SIGKILL', ['build' => $build->getId()]);
            posix_kill($build->getPid(), SIGKILL);
        }

        $this->producer->publish((string) $message);
    }
}