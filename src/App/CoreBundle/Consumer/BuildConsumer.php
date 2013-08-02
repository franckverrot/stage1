<?php

namespace App\CoreBundle\Consumer;

use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\Process\ProcessBuilder;

use App\CoreBundle\Entity\Build;

use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use OldSound\RabbitMqBundle\RabbitMq\Producer;
use PhpAmqpLib\Message\AMQPMessage;

use RuntimeException;
use Exception;

class BuildConsumer implements ConsumerInterface
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

        return (int) $query->getSingleScalarResult();
    }

    public function execute(AMQPMessage $message)
    {
        $body = json_decode($message->body);

        $buildRepo = $this->doctrine->getRepository('AppCoreBundle:Build');
        $build = $buildRepo->find($body->build_id);

        if (!$build) {
            throw new RuntimeException('Could not find Build#'.$body->build_id);
        }

        if (!$build->isScheduled()) {
            return true;
        }

        $build->setStatus(Build::STATUS_BUILDING);
        $this->persistAndFlush($build);

        $this->producer->publish(json_encode(['event' => 'build.started', 'data' => [
            'build' => [
                'id' => $build->getId()
            ],
            'project' => [
                'id' => $build->getProject()->getId(),
                'nb_pending_builds' => $this->getPendingBuildsCount($build->getProject())
            ]
        ]]));

        $webUiDebugMode = false;

        try {
            if (!$webUiDebugMode) {
                $projectDir = realpath(__DIR__.'/../../../..');
                $builder = new ProcessBuilder([
                    $projectDir.'/bin/build.sh',
                    $build->getId(),
                    $build->getProject()->getCloneUrl(),
                    $build->getProject()->getOwner()->getAccessToken(),
                    $build->getImageName()
                ]);

                $process = $builder->getProcess();

                echo 'running '.$process->getCommandLine().PHP_EOL;
                $process->run();

                list($imageId, $containerId, $port) = explode(PHP_EOL, trim($process->getOutput()));
            } else {
                $containerId = null;
                $imageId = null;
                $port = null;
            }

            $build->setContainerId($containerId);
            $build->setImageId($imageId);
            $build->setStatus(Build::STATUS_RUNNING);
            $build->setUrl('http://stage1:'.$port);

            $queryBuilder = $this->doctrine->getRepository('AppCoreBundle:Build')->createQueryBuilder('b');

            if (!$webUiDebugMode) {
                $previousBuild = $queryBuilder
                    ->select()
                    ->where($queryBuilder->expr()->eq('b.project', '?1'))
                    ->andWhere($queryBuilder->expr()->eq('b.ref', '?2'))
                    ->andWhere($queryBuilder->expr()->eq('b.status', '?3'))
                    ->setParameters([
                        1 => $build->getProject()->getId(),
                        2 => $build->getRef(),
                        3 => Build::STATUS_RUNNING,
                    ])
                    ->getQuery()
                    ->getSingleResult();

                $builder = new ProcessBuilder([$projectDir.'/bin/stop.sh', $previousBuild->getContainerId(), $previousBuild->getImageId()]);
                $process = $builder->getProcess();

                echo 'running '.$process->getCommandLine().PHP_EOL;

                $process->run();
            }

            $queryBuilder
                ->update()
                ->set('b.status', '?1')
                ->where($queryBuilder->expr()->eq('b.project', '?2'))
                ->andWhere($queryBuilder->expr()->eq('b.ref', '?3'))
                ->andWhere($queryBuilder->expr()->eq('b.status', '?4'))
                ->setParameters([
                    1 => Build::STATUS_OBSOLETE,
                    2 => $build->getProject()->getId(),
                    3 => $build->getRef(),
                    4 => Build::STATUS_RUNNING
                ])
                ->getQuery()
                ->execute();

        } catch (Exception $e) {
            $build->setStatus(Build::STATUS_FAILED);
            $build->setMessage($e->getMessage());
        }

        $this->persistAndFlush($build);

        $this->producer->publish(json_encode(['event' => 'build.finished', 'data' => [
            'build' => [
                'id' => $build->getId(),
                'status' => $build->getStatus(),
                'status_label' => $build->getStatusLabel(),
                'status_label_class' => $build->getStatusLabelClass(),
                'url' => $build->getUrl(),
            ],
            'project' => [
                'id' => $build->getProject()->getId(),
                'nb_pending_builds' => $this->getPendingBuildsCount($build->getProject()),
            ]
        ]]));
    }
}