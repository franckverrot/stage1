<?php

namespace App\CoreBundle\Github;

use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use Guzzle\Http\Client;
use Doctrine\Common\Util\Inflector;

use App\CoreBundle\SshKeys;
use App\CoreBundle\Entity\User;
use App\CoreBundle\Entity\Project;
use App\CoreBundle\Entity\Branch;
use App\CoreBundle\Value\ProjectAccess;

use Closure;
use Redis;

class Import
{
    private $client;

    private $doctrine;

    private $project;

    private $user;

    private $redis;

    private $router;

    private $accessToken;

    private $initialProjectAccess;

    private $projectAccessToken;

    private $feature_ip_access_list = false;

    private $feature_token_access_list = true;

    public function __construct(Client $client, RegistryInterface $doctrine, Redis $redis, Router $router)
    {
        $this->client = $client;
        $this->doctrine = $doctrine;
        $this->redis = $redis;
        $this->router = $router;
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

    public function setInitialProjectAccess(ProjectAccess $initialProjectAccess)
    {
        $this->initialProjectAccess = $initialProjectAccess;
    }

    public function getProjectAccessToken()
    {
        return $this->projectAccessToken;
    }

    public function setFeatureIpAccessList($bool)
    {
        $this->feature_ip_access_list = (bool) $bool;
    }

    public function setFeatureTokenAccessList($bool)
    {
        $this->feature_token_access_list = (bool) $bool;
    }

    public function setAccessToken($accessToken)
    {
        $this->accessToken = $accessToken;
        $this->client->setDefaultOption('headers/Authorization', 'token '.$accessToken);
    }

    public function getAccessToken()
    {
        return $this->accessToken;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function setUser(User $user)
    {
        $this->user = $user;

        if (strlen($user->getAccessToken()) > 0) {
            $this->setAccessToken($user->getAccessToken());
        }
    }

    public function import($githubFullName, Closure $callback = null)
    {
        $project = new Project();
        $project->setGithubFullName($githubFullName);

        foreach ($this->getSteps() as $step) {
            if (null !== $callback) {
                $callback($step);
            }

            $method = 'do'.Inflector::classify($step['id']);
            $this->$method($project);
        }

        $em = $this->doctrine->getManager();
        $em->persist($project);
        $em->flush();

        return $project;
    }

    /**
     * @todo @project_access refactor
     */
    private function grantProjectAccess(Project $project, ProjectAccess $access)
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
                'header' => 'Authorization: token '.$this->getAccessToken()."\r\n".
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
                'header' => 'Authorization: token '.$this->getAccessToken()."\r\n"
            ],
        ])));
    }

    private function generateUrl($route, $parameters = array(), $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH)
    {
        return $this->router->generate($route, $parameters, $referenceType);
    }

    # @todo use github api instead of relying on request parameters
    private function doInspect(Project $project)
    {
        $request = $this->client->get('/repos/'.$project->getGithubFullName());
        $response = $request->send();

        $infos = $response->json();

        # @todo @normalize
        $project->setSlug(preg_replace('/[^a-z0-9\-]/', '-', strtolower($infos['full_name'])));

        $project->setGithubId($infos['id']);
        $project->setGithubOwnerLogin($infos['owner']['login']);
        $project->setGithubFullName($infos['full_name']);
        $project->setName($infos['name']);
        $project->setCloneUrl($infos['clone_url']);
        $project->setSshUrl($infos['ssh_url']);
        $project->setKeysUrl($infos['keys_url']);
        $project->setHooksUrl($infos['hooks_url']);

        # @todo does this really belong here?
        if (null !== $this->getUser()) {
            $project->addUser($this->getUser());
        }
    }

    private function doKeys(Project $project)
    {
        $keys = SshKeys::generate('Stage 1 - ' . $project->getFullName());

        $project->setPublicKey($keys['public']);
        $project->setPrivateKey($keys['private']);
    }

    private function doDeployKey(Project $project)
    {
        $keysUrl = str_replace('{/key_id}', '', $project->getKeysUrl());
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

    private function doWebhook(Project $project)
    {
        $hooksUrl = $project->getHooksUrl();
        $githubHookUrl = $this->generateUrl('app_core_hooks_github', [], true);

        # @todo the fuck is this?
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

    private function doBranches(Project $project)
    {
        $request = $this->client->get(['/repos/{owner}/{repo}/branches', [
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

    private function doAccess(Project $project)
    {
        if (null === $this->initialProjectAccess) {
            # no initial project access means the project
            # is public (most likely, a demo project)
            return;
        }

        # this is one special ip that cannot be revoked
        # it is used to keep the access list "existing"
        # thus activating auth on the staging areas
        # yes, it's a bit hacky.
        $this->grantProjectAccess($project, new ProjectAccess('0.0.0.0'));

        # this, however, is perfectly legit.
        $this->grantProjectAccess($project, $this->initialProjectAccess);

        # @todo @channel_auth move channel auth to an authenticator service
        # @todo @obsolete actually this might not be necessary because we don't
        #                 directly use the project's channel
        // $this->projectAccessToken = uniqid(mt_rand(), true);
        // $this->redis->sadd('channel:auth:' . $project->getChannel(), $this->projectAccessToken);
    }
}