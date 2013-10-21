<?php

namespace App\CoreBundle\Consumer;

use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\Process\ProcessBuilder;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

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

    private $router;

    public function __construct(RegistryInterface $doctrine, Producer $producer, Router $router)
    {
        $this->doctrine = $doctrine;
        $this->producer = $producer;
        $this->router = $router;

        echo '== initializing KillConsumer'.PHP_EOL;
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
        $this->getDoctrine()->resetManager();

        $body = json_decode($message->body);

        $buildRepo = $this->doctrine->getRepository('AppCoreBundle:Build');
        $build = $buildRepo->find($body->build_id);

        echo '<- received kill order for build #'.$body->build_id.PHP_EOL;

        if (!$build) {
            throw new RuntimeException('Could not find Build#'.$body->build_id);
        }

        if (!$build->isBuilding()) {
            $this->producer->publish(json_encode([
                'event' => 'build.finished',
                'channel' => $build->getProject()->getChannel(),
                'timestamp' => microtime(true),
                'data' => [
                    'build' => array_replace([
                        'schedule_url' => $this->generateUrl('app_core_project_schedule_build', ['id' => $build->getProject()->getId()]),
                        ], $build->asWebsocketMessage()),
                    'project' => [
                        'id' => $build->getProject()->getId(),
                        'nb_pending_builds' => $this->getPendingBuildsCount($build->getProject()),
                    ]
                ]
            ]));
            
            return true;
        }

        $builder = new ProcessBuilder([
            realpath(__DIR__.'/../../../../bin/build/kill.sh'),
            $build->getId()
        ]);
        $process = $builder->getProcess();

        echo '   running '.$process->getCommandLine().PHP_EOL;

        $process->run();

        if (!$process->isSuccessful()) {
            printf('   (%d) %s', $process->getExitCode(), $stderr = $process->getErrorOutput());

            if (trim($stderr) === 'Nothing to kill.') {
                echo '   marking build finished anyway.'.PHP_EOL;
                $build->setStatus(Build::STATUS_KILLED);
            }
        } else {
            $build->setStatus(Build::STATUS_KILLED);
        }

        $this->persistAndFlush($build);

        echo '-> sending build.finished'.PHP_EOL;

        $this->producer->publish(json_encode([
            'event' => 'build.finished',
            'channel' => $build->getProject()->getChannel(),
            'timestamp' => microtime(true),
            'data' => [
                'build' => array_replace([
                    'schedule_url' => $this->generateUrl('app_core_project_schedule_build', ['id' => $build->getProject()->getId()]),
                    ], $build->asWebsocketMessage()),
                'project' => [
                    'id' => $build->getProject()->getId(),
                    'nb_pending_builds' => $this->getPendingBuildsCount($build->getProject()),
                ]
            ]
        ]));
    }
}