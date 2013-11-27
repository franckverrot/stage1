<?php

namespace App\CoreBundle\EventListener\Build;

use App\CoreBundle\Event\BuildFinishedEvent;

use Psr\Log\LoggerInterface;

use Swift_Mailer;
use Swift_Message;

use Exception;

class BuildDemoEmailListener
{
    /**
     * @var Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var Swift_Mailer
     */
    private $mailer;

    /**
     * @param Swift_Mailer $mailer
     */
    public function __construct(LoggerInterface $logger, Swift_Mailer $mailer)
    {
        $this->logger = $logger;
        $this->mailer = $mailer;

        $logger->info('initialized '.__CLASS__);
    }

    /**
     * @param App\CoreBundle\Event\BuildFinishedEvent $event
     */
    public function onBuildFinished(BuildFinishedEvent $event)
    {
        $build = $event->getBuild();

        if ($build->isFailed() || !$build->isRunning() || !$build->isDemo()) {
            return;
        }

        $logger = $this->logger;
        $mailer = $this->mailer;

        $logger->info('sending demo build email for build #'.$build->getId());

        $buildUrl = $build->getUrl();

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
            $res = $mailer->send($message, $failed);

            $logger->info('sent '.$res.' emails for demo build #'.$build->getId());

            if (count($failed) > 0) {
                $logger->warn('failed sending '.count($failed).' recipients', $failed);
            }
        } catch (Exception $e) {
            $logger->error('failed sending emails for demo build #'.$build->getId(), ['exception' => $e]);
        }
    }
}