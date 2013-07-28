<?php

namespace App\CoreBundle\Consumer;

use Symfony\Bridge\Doctrine\RegistryInterface;

use App\CoreBundle\Entity\Build;

use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;

use RuntimeException;

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
    }
}