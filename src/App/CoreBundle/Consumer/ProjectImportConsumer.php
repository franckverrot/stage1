<?php

namespace App\CoreBundle\Consumer;

use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use OldSound\RabbitMqBundle\RabbitMq\Producer;
use PhpAmqpLib\Message\AMQPMessage;

use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use Symfony\Bundle\FrameworkBundle\Routing\Router;

use App\CoreBundle\Value\ProjectAccess;
use App\CoreBundle\Github\Import;

use Psr\Log\LoggerInterface;

class ProjectImportConsumer implements ConsumerInterface
{
    private $logger;

    private $importer;

    private $doctrine;

    private $websocket;

    private $router;

    private $websocketChannel;

    public function __construct(LoggerInterface $logger, Import $importer, RegistryInterface $doctrine, Producer $websocket, Router $router)
    {
        $this->logger = $logger;
        $this->importer = $importer;
        $this->doctrine = $doctrine;
        $this->websocket = $websocket;
        $this->router = $router;

        $logger->info('initialized '.__CLASS__, ['pid' => posix_getpid()]);
    }

    private function generateUrl($route, $parameters = array(), $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH)
    {
        return $this->router->generate($route, $parameters, $referenceType);
    }

    public function setWebsocketChannel($channel)
    {
        echo '   setting websocket channel (' . $channel . ')'.PHP_EOL;
        $this->websocketChannel = $channel;
    }

    public function getWebsocketChannel()
    {
        return $this->websocketChannel;
    }

    private function publish($event, $data = null)
    {
        echo '-> publishing "' . $event . '" to channel "' . $this->getWebsocketChannel(). '"'.PHP_EOL;

        $message = [
            'event' => $event,
            'channel' => $this->getWebsocketChannel(),
        ];

        if (null !== $data) {
            $message['data'] = $data;
        }

        $this->websocket->publish(json_encode($message));
    }

    public function execute(AMQPMessage $message)
    {
        echo '<- received import request'.PHP_EOL;

        $body = json_decode($message->body);

        $user = $this->doctrine->getRepository('Model:User')->find($body->user_id);

        $this->importer->setUser($user);
        $this->importer->setInitialProjectAccess(new ProjectAccess($body->client_ip, $body->session_id));

        $this->setWebsocketChannel($user->getChannel());

        echo '   found user #' . $user->getId().PHP_EOL;
        echo '   user channel is "' . $user->getChannel().'"'.PHP_EOL;
        echo '   using websocket channel "' . $this->getWebsocketChannel() . '"'.PHP_EOL;

        $this->publish('project.import.start', [
            'full_name' => $body->request->github_full_name,
            'steps' => $this->importer->getSteps(),
            'project_github_id' => $body->request->github_id,
        ]);

        $that = $this;

        $project = $this->importer->import($body->request->github_full_name, function($step) use ($that) {
            $that->publish('project.import.step', ['step' => $step['id']]);
        });

        if (false === $project) {
            $this->publish('project.import.finished');
        } else {
            $this->publish('project.import.finished', [
                // @todo this might not be necessary anymore to pass the websocket token/channel
                'websocket_token' => $this->importer->getProjectAccessToken(),
                'websocket_channel' => $project->getChannel(),
                'project_full_name' => $project->getFullName(),
                'project_url' => $this->generateUrl('app_core_project_show', ['id' => $project->getId()]),
                'project_github_id' => $project->getGithubId(),
            ]);
        }
    }
}