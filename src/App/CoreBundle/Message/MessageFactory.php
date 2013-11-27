<?php

namespace App\CoreBundle\Message;

use App\CoreBundle\Entity\Build;
use App\CoreBundle\Entity\BuildLog;

use Symfony\Component\Routing\RouterInterface;

class MessageFactory
{
    /**
     * @var Symfony\Component\Routing\RouterInterface
     */
    private $router;

    /**
     * @param Symfony\Component\Routing\RouterInterface $router
     */
    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    public function createBuildScheduled(Build $build)
    {
        $message = new BuildScheduledMessage($build);
        $message->setRouter($this->router);

        return $message;
    }

    /**
     * @param App\CoreBundle\Entity\Build $build
     */
    public function createBuildStarted(Build $build)
    {
        $message = new BuildStartedMessage($build);
        $message->setRouter($this->router);

        return $message;
    }

    /**
     * @param App\CoreBundle\Entity\Build $build
     */
    public function createBuildFinished(Build $build)
    {
        $message = new BuildFinishedMessage($build);
        $message->setRouter($this->router);

        return $message;
    }

    /**
     * @param App\CoreBundle\Entity\Build $build
     */
    public function createBuildCanceled(Build $build)
    {
        $message = new BuildCanceledMessage($build);
        $message->setRouter($this->router);

        return $message;
    }

    /**
     * @param App\CoreBundle\Entity\Build $build
     */
    public function createBuildKilled(Build $build)
    {
        $message = new BuildKilledMessage($build);
        $message->setRouter($this->router);

        return $message;
    }

    /**
     * @param App\CoreBundle\Entity\BuildLog $buildLog
     */
    public function createBuildLog(BuildLog $buildLog)
    {
        return new BuildLogMessage($buildLog);
    }

    /**
     * @param App\CoreBundle\Entity\Build $build
     * @param string $step
     */
    public function createBuildStepMessage(Build $build, $step)
    {
        return new BuildStepMessage($build, $step);
    }
}