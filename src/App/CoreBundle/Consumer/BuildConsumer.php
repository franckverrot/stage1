<?php

namespace App\CoreBundle\Consumer;

use Symfony\Bridge\Doctrine\RegistryInterface;

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
use Swift_TransportException;

use Doctrine\ORM\NoResultException;

use App\CoreBundle\Message\BuildStartedMessage;
use App\CoreBundle\Message\BuildFinishedMessage;
use App\CoreBundle\Message\BuildStepMessage;

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

    public function generateUrl($route, $parameters = array(), $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH)
    {
        return $this->router->generate($route, $parameters, $referenceType);
    }

    public function execute(AMQPMessage $message)
    {
        $body = json_decode($message->body);

        $build = $this->getBuildRepository()->find($body->build_id);

        $em = $this->doctrine->getManager();
        $buildRepository = $em->getRepository('AppCoreBundle:Build');

        $build = $buildRepository->find($body->build_id);

        if (!$build) {
            echo '[x] could not find build #'.$body->build_id;
            return;
        }

        $build->setStatus(Build::STATUS_BUILDING);

        $em->persist($build);
        $em->flush();

        $this->stopwatch->start($build->getChannel());

        $message = new BuildStartedMessage($build);
        $this->producer->publish((string) $message);

        try {
            $container = $this->getBuilder()->run($build);

            $build->setContainerId($container->getId());
            $build->setPort($container->getMappedPort(80)->getHostPort());

            $previousBuild = $buildRepository->findPreviousBuild($build);

            if ($previousBuild && $previousBuild->hasContainer()) {
                $message = new BuildStepMessage($build, 'stop_previous');
                $producer->publish((string) $message);

                $this->docker->getContainerManager()->stop($previousBuild->getContainer());
                $previousBuild->setStatus(Build::STATUS_OBSOLETE);
                $em->persist($previousBuild);
            }
        } catch (Exception $e) {
            $build->setStatus(Build::STATUS_FAILED);
            $build->setMessage($e->getMessage());
        }

        $event = $this->stopwatch->stop($build->getChannel());

        if (strlen($build->getHost()) === 0) {
            $build->setHost(sprintf($this->buildHostMask, $build->getBranchDomain()));
        }

        $build->setStartTime($event->getStartTime());
        $build->getEndTime($event->getEndTime());
        $build->setDuration($event->getDuration());
        $build->setMemoryUsage($event->getMemory());

        $em->persist($build);
        $em->flush();

        $message = new BuildFinishedMessage($build);
        $producer->publish((string) $message);

        return;

        #################################### LEGACY SHIT ####################################

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

            try {
                $res = $this->mailer->send($message, $failed);

                echo '   sent '.$res.' emails'.PHP_EOL;

                if (count($failed) > 0) {
                    echo '   failed '.count($failed).' recipients:'.PHP_EOL;
                    foreach ($failed as $recipient) {
                        echo '    - '.$recipient.PHP_EOL;
                    }
                }
            } catch (Swift_TransportException $e) {
                echo '   exception while trying to send an email'.PHP_EOL;
                echo '     '.$e->getMessage().PHP_EOL;
            }
        }
    }
}