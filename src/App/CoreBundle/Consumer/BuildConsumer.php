<?php

namespace App\CoreBundle\Consumer;

use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\Process\ProcessBuilder;

use App\CoreBundle\Entity\Build;

use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;

use RuntimeException;
use Exception;

class BuildConsumer implements ConsumerInterface
{
    private $doctrine;

    public function __construct(RegistryInterface $doctrine)
    {
        $this->doctrine = $doctrine;
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

    private function build($cloneUrl, $accessToken)
    {
        $builder = new ProcessBuilder(['/usr/bin/docker', 'run', '-d', 'symfony2', 'buildapp', $cloneUrl, $accessToken]);
        $builder->setTimeout(null);
        $process = $builder->getProcess();

        echo 'running '.$process->getCommandLine().PHP_EOL;

        $process->run();

        echo $process->getErrorOutput();

        $containerId = trim($process->getOutput());

        $builder = new ProcessBuilder(['/usr/bin/docker', 'wait', $containerId]);
        $process = $builder->getProcess();

        echo 'running '.$process->getCommandLine().PHP_EOL;

        $process->run();

        return $containerId;
    }


    public function run($imageId)
    {
        $builder = new ProcessBuilder(['/usr/bin/docker', 'run', '-d', '-p', '80', $imageId, 'runapp']);
        $process = $builder->getProcess();

        echo 'running '.$process->getCommandLine().PHP_EOL;

        $process->run();

        echo $process->getErrorOutput();

        return trim($process->getOutput());
    }

    private function commit($containerId, $name)
    {
        $builder = new ProcessBuilder(['/usr/bin/docker', 'commit', $containerId, $name]);
        $process = $builder->getProcess();

        echo 'running '.$process->getCommandLine().PHP_EOL;

        $process->run();

        echo $process->getErrorOutput();

        return trim($process->getOutput());
    }

    private function getPort($imageId, $port)
    {
        $builder = new ProcessBuilder(['/usr/bin/docker', 'port', $imageId, '80']);
        $process = $builder->getProcess();

        echo 'running '.$process->getCommandLine().PHP_EOL;

        $process->run();

        echo $process->getErrorOutput();

        return trim($process->getOutput());
    }

    private function doBuild(Build $build)
    {
        $cloneUrl = $build->getProject()->getCloneUrl();
        $accessToken = $build->getProject()->getOwner()->getAccessToken();

        $containerId = $this->build($cloneUrl, $accessToken);
        $imageId = $this->commit($containerId, $build->getImageName());
        $containerId = $this->run($imageId);

        $build->setContainerId($containerId);
        $build->setImageId($imageId);
        $build->setStatus(Build::STATUS_RUNNING);
        $build->setUrl('http://stage1:'.trim($this->getPort($containerId, 80)));
    }

    public function execute(AMQPMessage $message)
    {
        $body = json_decode($message->body);

        $build = $this->doctrine->getRepository('AppCoreBundle:Build')->find($body->build_id);

        if (!$build) {
            throw new RuntimeException('Could not find Build#'.$body->build_id);
        }

        if (!$build->isScheduled()) {
            return true;
        }

        $build->setStatus(Build::STATUS_BUILDING);
        $this->persistAndFlush($build);

        try {
            $this->doBuild($build);
        } catch (Exception $e) {
            $build->setStatus(Build::STATUS_FAILED);
            $build->setMessage($e->getMessage());
        }

        $this->persistAndFlush($build);
    }
}