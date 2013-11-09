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

use Swift_Mailer;
use Swift_Message;

use Doctrine\ORM\NoResultException;

class BuildConsumer implements ConsumerInterface
{
    private $doctrine;

    private $producer;

    private $router;

    private $buildTimeout = 0;

    private $buildHostMask;

    private $expectedMessages = 0;

    public function __construct(RegistryInterface $doctrine, Producer $producer, Router $router, Swift_Mailer $mailer, $buildHostMask)
    {
        $this->doctrine = $doctrine;
        $this->producer = $producer;
        $this->router = $router;
        $this->mailer = $mailer;
        $this->buildHostMask = $buildHostMask;

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

    private function findPreviousBuild(Build $build)
    {
        try {
            return $this->getBuildRepository()->findPreviousBuild($build);
        } catch (NoResultException $e) {
            printf('[x] Could not find a previous build for %s@%s', $build->getProject()->getFullName(), $build->getRef());
        }

        return null;
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
        $expectedMessages = $this->expectedMessages;

        # @todo check php version for $this binding in closures
        $process->run(function($type, $data) use ($producer, $build, $entityManager, $expectedMessages) {
            static $n = 0, $totalMessages = 0;

            $data = rtrim($data);
            $data = explode(PHP_EOL, $data);

            $totalMessages++;

            $progress = ($expectedMessages > 0) ? floor(($totalMessages / $expectedMessages) * 100) : null;

            echo '   got message '.$totalMessages.' / '.$expectedMessages.' ('.$progress.'%)'.PHP_EOL;

            foreach ($data as $line) {
                if (preg_match('/^\[websocket:(.+?):(.*)\]$/', $line, $matches)) {
                    echo '-> got websocket message'.PHP_EOL;
                    echo '   event: '.$matches[1].PHP_EOL;
                    echo '   data:  '.$matches[2].PHP_EOL;

                    // if (!$build->getStreamSteps()) {
                    //     echo PHP_EOL.'   step skipped because stream_steps is false';
                    //     return;
                    // } else {
                    //     echo '<- routing step'.PHP_EOL;
                    // }
                    
                    $producer->publish(json_encode([
                        'event' => $matches[1],
                        'channel' => $build->getChannel(),
                        'data' => [
                            'build' => $build->asWebsocketMessage(),
                            'announce' => json_decode($matches[2], true),
                            'progress' => $progress,
                        ]
                    ]));
                } else {
                    // if (!$build->getStreamOutput()) {
                    //     echo PHP_EOL.'   output skipped because stream_output is false';
                    //     return;
                    // } else {
                    //     echo '<- routing output'.PHP_EOL;
                    // }

                    $producer->publish(json_encode([
                        'event' => 'build.output',
                        'channel' => $build->getChannel(),
                        'data' => [
                            'build' => $build->asWebsocketMessage(),
                            'project' => $build->getProject()->asWebsocketMessage(),
                            'number' => $n++,
                            'content' => $line,
                            'progress' => $progress,
                        ]
                    ]));

                    $log = $build->appendLog($line, 'output', $type);
                    $entityManager->persist($log);                
                }
            }
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

        if (count($buildInfo) !== 3) {
            throw new InvalidArgumentException('Malformed build info: '.var_export($buildInfo, true));
        }

        list($imageId, $containerId, $port) = $buildInfo;

        $build->setContainerId($containerId);
        $build->setImageId($imageId);
        $build->setPort($port);

        $producer->publish(json_encode([
            'event' => 'build.step',
            'channel' => $build->getChannel(),
            'data' => [
                'build' => $build->asWebsocketMessage(),
                'announce' => ['step' => 'stop_previous'],
            ]
        ]));

        $previousBuild = $this->findPreviousBuild($build);

        if (null !== $previousBuild && $previousBuild->hasContainer()) {
            $builder = new ProcessBuilder([
                $projectDir.'/bin/build/stop.sh',
                $previousBuild->getContainerId(),
                $previousBuild->getImageId(),
                $previousBuild->getImageTag(),
            ]);
            $process = $builder->getProcess();

            echo 'stopping previous build container'.PHP_EOL;
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

        $build_start = time();

        $build = $this->getBuildRepository()->find($body->build_id);

        echo '<- received build order'.PHP_EOL;
        echo '   build started at '.$build_start.PHP_EOL;

        if (!$build || !$build->isScheduled()) {
            echo '[x] build is not "scheduled", skipping'.PHP_EOL;
            return;
        }

        $build->setStatus(Build::STATUS_BUILDING);

        if (strlen($build->getHost()) === 0) {
            $build->setHost(sprintf($this->buildHostMask, $build->getBranchDomain()));
        }

        $this->persistAndFlush($build);

        $previousBuild = $this->findPreviousBuild($build);

        if (null !== $previousBuild) {
            echo '   found previous build (#'.$previousBuild->getId().')'.PHP_EOL;
            $this->expectedMessages = count($previousBuild->getLogs());
        }

        echo '   expecting '.$this->expectedMessages.' messages'.PHP_EOL;

        $this->producer->publish(json_encode([
            'event' => 'build.started',
            'channel' => $build->getChannel(),
            'timestamp' => microtime(true),
            'data' => [
                'progress' => 0,
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

        $build_end = time();

        echo '   build finished at '.$build_end.PHP_EOL;
        echo '   build duration: '.($build_end - $build_start).PHP_EOL;

        $build->setDuration($build_end - $build_start);

        $this->persistAndFlush($build);

        if ($build->isRunning() && $build->isDemo()) {
            $buildUrl = $build->getUrl();

            echo '   sending demo build email to "'.$build->getDemo()->getEmail().'"'.PHP_EOL;

            $message = Swift_Message::newInstance()
                ->setSubject('Your Stage1 demo build is ready')
                ->setFrom('geoffrey@stage1.io')
                ->setTo($build->getDemo()->getEmail())
                ->setBody(<<<EOM
Your demo build is ready and you can access it through the following url:

$buildUrl

-- 
Geoffrey
EOM
);
            $failed = [];
            $res = $this->mailer->send($message, $failed);

            echo '   sent '.$res.' emails'.PHP_EOL;

            if (count($failed) > 0) {
                echo '   failed '.count($failed).' recipients:'.PHP_EOL;
                foreach ($failed as $recipient) {
                    echo '    - '.$recipient.PHP_EOL;
                }
            }
        }

        $this->producer->publish(json_encode([
            'event' => 'build.finished',
            'channel' => $build->getChannel(),
            'timestamp' => microtime(true),
            'data' => [
                'progress' => 100,
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