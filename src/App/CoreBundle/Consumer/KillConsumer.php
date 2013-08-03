<?php

namespace App\CoreBundle\Consumer;

use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\Process\ProcessBuilder;

use App\CoreBundle\Entity\Build;

use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use OldSound\RabbitMqBundle\RabbitMq\Producer;
use PhpAmqpLib\Message\AMQPMessage;

use InvalidArgumentException;
use RuntimeException;
use Exception;

class KillConsumer implements ConsumerInterface
{
    private $doctrine;

    private $producer;

    public function __construct(RegistryInterface $doctrine, Producer $producer)
    {
        $this->doctrine = $doctrine;
        $this->producer = $producer;
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

    public function getPendingBuildsCount($project)
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
        $body = json_decode($message->body);

        $buildRepo = $this->doctrine->getRepository('AppCoreBundle:Build');
        $build = $buildRepo->find($body->build_id);

        if (!$build) {
            throw new RuntimeException('Could not find Build#'.$body->build_id);
        }

        if (!$build->isBuilding()) {
            $this->producer->publish(json_encode(['event' => 'build.finished', 'data' => [
                'build' => $build->asWebsocketMessage(),
                'project' => [
                    'id' => $build->getProject()->getId(),
                    'nb_pending_builds' => $this->getPendingBuildsCount($build->getProject()),
                ]
            ]]));
            
            return true;
        }

        $builder = new ProcessBuilder([
            realpath(__DIR__.'/../../../../bin/kill.sh'),
            $build->getId()
        ]);
        $process = $builder->getProcess();

        echo 'running '.$process->getCommandLine().PHP_EOL;

        $process->run();

        if (!$process->isSuccessful()) {
            printf('%d: %s', $process->getExitCode(), $stderr = $process->getErrorOutput());

            if (trim($stderr) === 'Nothing to kill.') {
                $build->setStatus(Build::STATUS_KILLED);
                $this->persistAndFlush($build);

                $this->producer->publish(json_encode(['event' => 'build.finished', 'data' => [
                    'build' => $build->asWebsocketMessage(),
                    'project' => [
                        'id' => $build->getProject()->getId(),
                        'nb_pending_builds' => $this->getPendingBuildsCount($build->getProject()),
                    ]
                ]]));
            }
        }
    }
}