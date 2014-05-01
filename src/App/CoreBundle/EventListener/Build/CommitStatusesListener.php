<?php

namespace App\CoreBundle\EventListener\Build;

use App\CoreBundle\Event\BuildFinishedEvent;
use App\Model\Build;
use Guzzle\Http\Client;
use Psr\Log\LoggerInterface;

use Exception;

/**
 * Marks a previous build for a same ref obsolete
 */
class CommitStatusesListener
{
    /**
     * @var Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var Guzzle\Http\Client
     */
    private $github;

    /**
     * @var boolean
     */
    private $enabled;

    /**
     * @param Psr\Log\LoggerInterface   $logger
     * @param Guzzle\Http\Client        $github
     * @param Docker\Docker
     */
    public function __construct(LoggerInterface $logger, Client $github, $enabled)
    {
        // $github->setDefaultOption('headers/Accept', 'application/vnd.github.v3');
        $github->setDefaultOption('headers/Accept', 'application/vnd.github.she-hulk-preview+json');

        $this->logger = $logger;
        $this->github = $github;
        $this->enabled = $enabled;

        $logger->info('initialized '.__CLASS__);
    }

    public function onBuildFinished(BuildFinishedEvent $event)
    {
        if (!$this->enabled) {
            return;
        }
        
        $build = $event->getBuild();

        if (!$build->isRunning()) {
            return;
        }

        if (strlen($build->getHash()) === 0) {
            $this->logger->info('skipping commit status because of empty commit hash');
            return;
        }

        $project = $build->getProject();

        $this->github->setDefaultOption('headers/Authorization', 'token '.$project->getUsers()->first()->getAccessToken());

        $request = $this->github->post(['/repos/'.$project->getGithubFullName().'/statuses/{sha}', [
            'sha' => $build->getHash(),
        ]]);

        $request->setBody(json_encode([
            'state' => 'success',
            'target_url' => $build->getUrl(),
            'description' => 'Stage1 instance ready',
            'context' => 'stage1',
        ]));

        $this->logger->info('sending commit status', [
            'build' => $build->getId(),
            'project' => $project->getGithubFullNAme(),
            'sha' => $build->getHash(),
        ]);

        try {
            $request->send();
        } catch (\Guzzle\Http\Exception\ClientErrorResponseException $e) {
            $this->logger->error('error sending commit status', [
                'exception_class' => get_class($e),
                'exception_message' => $e->getMessage(),
                'url' => $e->getRequest()->getUrl(),
                'response' => (string) $e->getResponse()->getBody(),
            ]);
        }
    }
}