<?php

namespace App\CoreBundle\Consumer;

use Symfony\Bridge\Doctrine\RegistryInterface;

use App\CoreBundle\Entity\BuildLog;

use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;

use DateTime;

class BuildLogConsumer implements ConsumerInterface
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

    public function execute(AMQPMessage $message)
    {
        $body = json_decode($message->body);

        echo '<- received log line for build #'.$body->build_id.PHP_EOL;

        $build = $this->getDoctrine()->getRepository('AppCoreBundle:Build')->find($body->build_id);

        if (!$build) {
            echo '  could not find build';
            return;
        }

        if (!$build->isRunning()) {
            echo '   build is not running';
            return;
        }

        $buildLog = new BuildLog();
        $buildLog->setMessage($body->content);
        $buildLog->setBuild($build);

        $now = new DateTime();

        $buildLog->setCreatedAt($now);
        $buildLog->setUpdatedAt($now);

        $build->addLog($buildLog);

        $em = $this->getDoctrine()->getManager();
        $em->persist($build);
        $em->persist($buildLog);
        $em->flush();
    }
}