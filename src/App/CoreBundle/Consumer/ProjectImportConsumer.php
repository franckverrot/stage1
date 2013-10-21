<?php

namespace App\CoreBundle\Consumer;

use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Bundle\FrameworkBundle\Routing\Router;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use OldSound\RabbitMqBundle\RabbitMq\Producer;
use PhpAmqpLib\Message\AMQPMessage;
use Guzzle\Http\Client;
use Doctrine\Common\Util\Inflector;

use App\CoreBundle\SshKeys;
use App\CoreBundle\Entity\User;
use App\CoreBundle\Entity\Project;
use App\CoreBundle\Entity\Branch;
use App\CoreBundle\Value\ProjectAccess;

use DateTime;
use Redis;

class ProjectImportConsumer implements ConsumerInterface
{
    private $client;

    private $doctrine;

    private $websocket;

    private $router;

    private $websocketChannel;

    private $websocketToken;

    private $user;

    private $redis;

    private $feature_ip_access_list = false;

    private $feature_token_access_list = true;

    public function __construct(Client $client, RegistryInterface $doctrine, Producer $websocket, Router $router, Redis $redis)
    {
        $this->client = $client;
        $this->doctrine = $doctrine;
        $this->websocket = $websocket;
        $this->router = $router;
        $this->redis = $redis;

        echo '== initializing ProjectImportConsumer'.PHP_EOL;
    }

    public function getDoctrine()
    {
        return $this->doctrine;
    }

    public function setFeatureIpAccessList($bool)
    {
        $this->feature_ip_access_list = (bool) $bool;
    }

    public function setFeatureTokenAccessList($bool)
    {
        $this->feature_token_access_list = (bool) $bool;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function setUser(User $user)
    {
        echo '   setting websocket channel from user (' . $user->getChannel() . ')'.PHP_EOL;
        $this->setWebsocketChannel($user->getChannel());
        $this->user = $user;
    }

    /**
     * @todo @project_access refactor
     */
    protected function grantProjectAccess(Project $project, ProjectAccess $access)
    {
        $args = ['auth:'.$project->getSlug()];

        if ($this->feature_ip_access_list || $access->getIp() === '0.0.0.0') {
            $args[] = $access->getIp();
        }

        if ($this->feature_token_access_list) {
            $args[] = $access->getToken();
        }

        $args = array_filter($args, function($arg) { return strlen($arg) > 0; });

        return call_user_func_array([$this->redis, 'sadd'], $args);
    }

    /**
     * @todo @github refactor
     */
    private function github_post($url, $payload)
    {
        return json_decode(file_get_contents($url, false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'content' => json_encode($payload),
                'header' => 'Authorization: token '.$this->getUser()->getAccessToken()."\r\n".
                            "Content-Type: application/json\r\n"
            ],
        ])));
    }

    /**
     * @todo @github refactor
     */
    private function github_get($url)
    {
        return json_decode(file_get_contents($url, false, stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'Authorization: token '.$this->getUser()->getAccessToken()."\r\n"
            ],
        ])));
    }

    public function generateUrl($route, $parameters = array(), $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH)
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

    public function getSteps()
    {
        return [
            ['id' => 'inspect', 'label' => 'Inspecting project'],
            ['id' => 'keys', 'label' => 'Generating keys'],
            ['id' => 'deploy_key', 'label' => 'Adding deploy key'],
            ['id' => 'webhook', 'label' => 'Configuring webhook'],
            ['id' => 'branches', 'label' => 'Importing branches'],
            ['id' => 'access', 'label' => 'Granting default access'],
        ];
    }

    # @todo use github api instead of relying on request parameters
    public function doInspect(Project $project, $body, Client $client)
    {
        # @todo @normalize
        $project->setSlug(preg_replace('/[^a-z0-9\-]/', '-', strtolower($body->request->github_full_name)));

        $project->setGithubId($body->request->github_id);
        $project->setGithubOwnerLogin($body->request->github_owner_login);
        $project->setGithubFullName($body->request->github_full_name);
        $project->setName($body->request->name);
        $project->setCloneUrl($body->request->clone_url);
        $project->setSshUrl($body->request->ssh_url);

        $project->addUser($this->getUser());
    }

    public function doKeys(Project $project, $body, Client $client)
    {
        $keys = SshKeys::generate('Stage 1 - ' . $project->getFullName());

        $project->setPublicKey($keys['public']);
        $project->setPrivateKey($keys['private']);
    }

    public function doDeployKey(Project $project, $body, Client $client)
    {
        $keysUrl = str_replace('{/key_id}', '', $body->request->keys_url);
        $keys = $this->github_get($keysUrl);

        $deployKey = $project->getPublicKey();
        $deployKey = substr($deployKey, 0, strrpos($deployKey, ' '));

        foreach ($keys as $_) {
            if ($_->key === $deployKey) {
                $key = $_;
                break;
            }
        }

        if (!isset($key)) {
            $key = $this->github_post($keysUrl, [
                'key' => $deployKey,
                'title' => 'Stage1 Deploy Key (added by ' . $this->getUser()->getUsername() .' )',
            ]);
        }

        $project->setGithubDeployKeyId($key->id);

    }

    public function doWebhook(Project $project, $body, Client $client)
    {
        $hooksUrl = $body->request->hooks_url;
        $githubHookUrl = $this->generateUrl('app_core_hooks_github', [], true);

        $githubHookUrl = str_replace('http://localhost', 'http://stage1.io', $githubHookUrl);
        $githubHookUrl = str_replace('http://stage1.io', 'http://stage1:stage1@stage1.io', $githubHookUrl);

        $hooks = $this->github_get($hooksUrl);

        foreach ($hooks as $_) {
            if ($_->name === 'web' && $_->config->url === $githubHookUrl) {
                $hook = $_;
                break;
            }
        }

        if (!isset($hook)) {
            $hook = $this->github_post($hooksUrl, [
                'name' => 'web',
                'active' => true,
                'events' => ['push'],
                'config' => ['url' => $githubHookUrl, 'content_type' => 'json'],
            ]);                
        }

        $project->setGithubHookId($hook->id);
    }

    public function doBranches(Project $project, $body, Client $client)
    {
        $request = $client->get(['/repos/{owner}/{repo}/branches', [
            'owner' => $project->getGithubOwnerLogin(),
            'repo' => $project->getName(),
        ]]);

        $response = $request->send();

        foreach ($response->json() as $data) {
            $branch = new Branch();
            $branch->setName($data['name']);

            $branch->setProject($project);
            $project->addBranch($branch);
        }
    }

    public function doAccess(Project $project, $body, Client $client)
    {
        # this is one special ip that cannot be revoked
        # it is used to keep the access list "existing"
        # thus activating auth on the staging areas
        # yes, it's a bit hacky.
        $this->grantProjectAccess($project, new ProjectAccess('0.0.0.0'));

        # this, however, is perfectly legit.
        $this->grantProjectAccess($project, new ProjectAccess($body->client_ip, $body->session_id));

        # @todo @channel_auth move channel auth to an authenticator service
        $this->websocketToken = uniqid(mt_rand(), true);
        $this->redis->sadd('channel:auth:' . $project->getChannel(), $this->websocketToken);
    }

    public function execute(AMQPMessage $message)
    {
        echo '<- received import request'.PHP_EOL;

        $body = json_decode($message->body);

        $this->setUser($this->doctrine->getRepository('AppCoreBundle:User')->find($body->user_id));

        $client = clone $this->client;
        $client->setDefaultOption('headers/Authorization', 'token '.$this->getUser()->getAccessToken());

        echo '   found user #' . $this->getUser()->getId().PHP_EOL;
        echo '   user channel is "' . $this->getUser()->getChannel().'"'.PHP_EOL;
        echo '   using websocket channel "' . $this->getWebsocketChannel() . '"'.PHP_EOL;

        $this->publish('project.import.start', [
            'full_name' => $body->request->github_full_name,
            'steps' => $this->getSteps(),
            'project_github_id' => $body->request->github_id,
        ]);

        $project = new Project();

        foreach ($this->getSteps() as $step) {
            $this->publish('project.import.step', ['step' => $step['id']]);
            $method = 'do'.Inflector::classify($step['id']);
            $this->$method($project, $body, $client);
        }

        $em = $this->getDoctrine()->getManager();
        $em->persist($project);
        $em->flush();

        $this->publish('project.import.finished', [
            'websocket_token' => $this->websocketToken,
            'websocket_channel' => $project->getChannel(),
            'project_full_name' => $project->getFullName(),
            'project_url' => $this->generateUrl('app_core_project_show', ['id' => $project->getId()]),
            'project_github_id' => $project->getGithubId(),
        ]);
    }
}