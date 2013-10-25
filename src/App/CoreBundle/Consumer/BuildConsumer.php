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

use Psr\Log\LoggerInterface;

use InvalidArgumentException;
use RuntimeException;
use Exception;

class BuildConsumer implements ConsumerInterface
{
    private $doctrine;

    private $producer;

    private $router;

    private $buildTimeout = 0;

    public function __construct(RegistryInterface $doctrine, Producer $producer, Router $router)
    {
        $this->doctrine = $doctrine;
        $this->producer = $producer;
        $this->router = $router;

        echo '== initializing BuildConsumer'.PHP_EOL;
    }

    public function setBuildTimeout($buildTimeout)
    {
        $this->buildTimeout = (integer) $buildTimeout;
    }

    public function getDoctrine()
    {
        return $this->doctrine;
    }

    private function getBuildRepository()
    {
        return $this->getDoctrine()->getRepository('AppCoreBundle:Build');
    }

    private function persistAndFlush($entity)
    {
        $em = $this->getDoctrine()->getManager();
        $em->persist($entity);
        $em->flush();
    }

    public function generateUrl($route, $parameters = array(), $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH)
    {
        return $this->router->generate($route, $parameters, $referenceType);
    }

    private function doBuild(Build $build)
    {
        $buildFile = function($type) use ($build) {
            $path = '/tmp/stage1/build/'.$build->getId().'/'.$type;

            if (!file_exists(dirname($path))) {
                mkdir($path, 0777, true);
            }

            return $path;
        };

        $projectDir = realpath(__DIR__.'/../../../..');
        $builder = new ProcessBuilder([
            $projectDir.'/bin/build/start.sh',
            $build->getId()
        ]);

        # @todo add a Build::STATUS_TIMEOUT status
        $builder->setTimeout($this->buildTimeout);
        // $builder->setEnv('STAGE1_DEBUG', 1);

        $process = $builder->getProcess();
        $process->setCommandLine($process->getCommandLine());

        echo 'running '.$process->getCommandLine().' with timeout '.$this->buildTimeout.PHP_EOL;
        
        $producer = $this->producer;
        $entityManager = $this->getDoctrine()->getManager();

        # @todo check php version for $this binding in closures
        $process->run(function($type, $data) use ($producer, $build, $entityManager) {
            static $n = 0;
            $producer->publish(json_encode([
                'event' => 'build.output',
                'channel' => $build->getChannel(),
                'data' => [
                    'build' => $build->asWebsocketMessage(),
                    'project' => $build->getProject()->asWebsocketMessage(),
                    'number' => $n++,
                    'content' => $data,
                ]
            ]));

            $log = $build->appendLog($data, 'output', $type);
            $entityManager->persist($log);
        });

        $entityManager->flush();

        if (!$process->isSuccessful()) {
            if (in_array($process->getExitCode(), [137, 143])) {
                return Build::STATUS_KILLED;
            }

            return false;
        }

        $build->setExitCode($process->getExitCode());
        $build->setExitCodeText($process->getExitCodeText());

        $buildInfo = explode(PHP_EOL, trim(file_get_contents($buildFile('info'))));
        unlink($buildFile('info'));

        if (count($buildInfo) !== 4) {
            throw new InvalidArgumentException('Malformed build info: '.var_export($buildInfo, true));
        }

        list($imageId, $containerId, $port, $url) = $buildInfo;

        $build->setContainerId($containerId);
        $build->setImageId($imageId);
        $build->setPort($port);
        $build->setUrl($url);

        $queryBuilder = $this->doctrine->getRepository('AppCoreBundle:Build')->createQueryBuilder('b');

        if (null !== ($previousBuild = $this->getBuildRepository()->findPreviousBuild($build)) && $previousBuild->hasContainer()) {
            $builder = new ProcessBuilder([
                $projectDir.'/bin/build/stop.sh',
                $previousBuild->getContainerId(),
                $previousBuild->getImageId(),
                $previousBuild->getImageTag(),
            ]);
            $process = $builder->getProcess();

            echo 'running '.$process->getCommandLine().PHP_EOL;

            $process->run();

            $previousBuild->setStatus(Build::STATUS_OBSOLETE);
            $this->persistAndFlush($previousBuild);
        }

        return true;
    }

    public function execute(AMQPMessage $message)
    {
        $body = json_decode($message->body);

        $build = $this->getBuildRepository()->find($body->build_id);

        echo '<- received build order'.PHP_EOL;

        if (!$build || !$build->isScheduled()) {
            echo '[x] build is not "scheduled", skipping'.PHP_EOL;
            return;
        }

        $build->setStatus(Build::STATUS_BUILDING);
        $this->persistAndFlush($build);

        $this->producer->publish(json_encode([
            'event' => 'build.started',
            'channel' => $build->getChannel(),
            'timestamp' => microtime(true),
            'data' => [
                'build' => array_replace([
                    'kill_url' => $this->generateUrl('app_core_build_kill', ['id' => $build->getId()]),
                    'show_url' => $this->generateUrl('app_core_build_show', ['id' => $build->getId()]),
                ], $build->asWebsocketMessage()),
                'project' => [
                    'id' => $build->getProject()->getId(),
                    'nb_pending_builds' => $this->getBuildRepository()->countPendingBuildsByProject($build->getProject())
                ]
            ]
        ]));

        try {
            $res = $this->doBuild($build);

            if (true === $res) {
                $res = Build::STATUS_RUNNING;
            } elseif (false === $res) {
                $res = Build::STATUS_FAILED;
            }

            $build->setStatus($res);
        } catch (Exception $e) {
            echo 'got exception "'.get_class($e).PHP_EOL.PHP_EOL;
            echo $e->getMessage();
            echo PHP_EOL.PHP_EOL;

            $build->setStatus(Build::STATUS_FAILED);
            $build->setMessage($e->getMessage());

            if (!$this->getDoctrine()->getManager()->isOpen()) {
                $this->getDoctrine()->resetManager();
            }
        }

        $this->persistAndFlush($build);

        $this->producer->publish(json_encode([
            'event' => 'build.finished',
            'channel' => $build->getChannel(),
            'timestamp' => microtime(true),
            'data' => [
                'build' => array_replace([
                    'schedule_url' => $this->generateUrl('app_core_project_schedule_build', ['id' => $build->getProject()->getId()]),
                    ], $build->asWebsocketMessage()),
                'project' => [
                    'id' => $build->getProject()->getId(),
                    'nb_pending_builds' => $this->getBuildRepository()->countPendingBuildsByProject($build->getProject()),
                ]
            ]
        ]));
    }
}