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

        $this->client->setDefaultOption('headers/Accept', 'application/vnd.github.v3');
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

        # @todo @slug
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
        $keys = SshKeys::generate();

        $project->setPublicKey($keys['public']);
        $project->setPrivateKey($keys['private']);
    }

    private function doDeployKey(Project $project)
    {
        $request = $this->client->get($project->getKeysUrl());
        $response = $request->send();

        $keys = $response->json();
        $projectDeployKey = $project->getPublicKey();

        $scheduleDelete = [];

        foreach ($keys as $key) {
            if ($key['key'] === $projectDeployKey) {
                $installedKey = $key;
                continue;
            }

            if (strpos($key['title'], 'stage1.io') === 0) {
                $scheduleDelete[] = $key;
            }
        }

        if (!isset($installedKey)) {
            $request = $this->client->post($project->getKeysUrl());
            $request->setBody(json_encode([
                'key' => $projectDeployKey,
                'title' => 'stage1.io (added by '.$this->getUser()->getUsername().')',
            ]), 'application/json');

            $response = $request->send();
            $installedKey = $response->json();
        }

        $project->setGithubDeployKeyId($installedKey['id']);

        // @todo
        // if (count($scheduleDelete) > 0) {
        //     foreach ($scheduleDelete as $key) {
        //         $request = $this->client->delete([$project->getKeysUrl(), ['key_id' => $key['id']]]);
        //         $response = $request->send();
        //     }
        // }
    }

    private function doWebhook(Project $project)
    {
        $githubHookUrl = $this->generateUrl('app_core_hooks_github', [], true);

        # @todo the fuck is this?
        $githubHookUrl = str_replace('http://localhost', 'http://stage1.io', $githubHookUrl);

        $request = $this->client->get($project->getHooksUrl());
        $response = $request->send();

        $hooks = $response->json();

        foreach ($hooks as $hook) {
            if ($hook['name'] === 'web' && $hook['config']['url'] === $githubHookUrl) {
                $installedHook = $hook;
                break;
            }
        }

        if (!isset($installedHook)) {
            $request = $this->client->post($project->getHooksUrl());
            $request->setBody(json_encode([
                'name' => 'web',
                'active' => true,
                'events' => ['push'],
                'config' => ['url' => $githubHookUrl, 'content_type' => 'json'],
            ]), 'application/json');

            $response = $request->send();
            $installedHook = $response->json();
        }

        $project->setGithubHookId($installedHook['id']);
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