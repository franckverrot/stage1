<?php

namespace App\CoreBundle\Message;

use App\Model\Build;
use App\Model\BuildLog;

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

    public function createBuildMessage(Build $build, $message)
    {
        $message = new BuildMessage($build, $message);
        
        return $message;
    }

    public function createBuildScheduled(Build $build)
    {
        $message = new BuildScheduledMessage($build);
        $message->setRouter($this->router);

        return $message;
    }

    /**
     * @param App\Model\Build $build
     */
    public function createBuildStarted(Build $build)
    {
        $message = new BuildStartedMessage($build);
        $message->setRouter($this->router);

        return $message;
    }

    /**
     * @param App\Model\Build $build
     */
    public function createBuildFinished(Build $build)
    {
        $message = new BuildFinishedMessage($build);
        $message->setRouter($this->router);

        return $message;
    }

    /**
     * @param App\Model\Build $build
     */
    public function createBuildCanceled(Build $build)
    {
        $message = new BuildCanceledMessage($build);
        $message->setRouter($this->router);

        return $message;
    }

    /**
     * @param App\Model\Build $build
     */
    public function createBuildKilled(Build $build)
    {
        $message = new BuildKilledMessage($build);
        $message->setRouter($this->router);

        return $message;
    }

    /**
     * @param App\Model\BuildLog $buildLog
     */
    public function createBuildLog(BuildLog $buildLog)
    {
        return new BuildLogMessage($buildLog);
    }

    /**
     * @param App\Model\Build $build
     * @param string $step
     */
    public function createBuildStepMessage(Build $build, $step)
    {
        return new BuildStepMessage($build, $step);
    }
}