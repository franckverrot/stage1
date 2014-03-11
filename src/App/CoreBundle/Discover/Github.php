<?php

# @todo move to App\CoreBundle\Github\Discover

namespace App\CoreBundle\Discover;

use Guzzle\Http\Client;
use Guzzle\Http\Message\Response;

use Psr\Log\LoggerInterface;

use App\Model\User;

class Github
{
    private $client;

    private $projectsCache = [];

    private $importableProjects = [];

    private $nonImportableProjects = [];

    private $logger;

    public function __construct(Client $client, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
    }

    public function addImportableProject($fullName)
    {
        $this->importableProjects[$fullName] = $this->getProjectInfo($fullName);
    }

    public function getImportableProjects()
    {
        return $this->importableProjects;
    }

    public function addNonImportableProject($fullName, $reason)
    {
        $this->nonImportableProjects[] = [
            'fullName' => $fullName,
            'reason' => $reason
        ];
    }

    public function getNonImportableProjects()
    {
        return $this->nonImportableProjects;
    }

    private function cacheProjectInfo($data)
    {
        $this->projectsCache[$data['full_name']] = array(
            'name' => $data['name'],
            'github_full_name' => $data['full_name'],
            'github_owner_login' => $data['owner']['login'],
            'github_owner_avatar_url' => $data['owner']['avatar_url'],
            'github_id' => $data['id'],
            'clone_url' => $data['clone_url'],
            'ssh_url' => $data['ssh_url'],
            'hooks_url' => $data['hooks_url'],
            'keys_url' => $data['keys_url'],
            'exists' => false,
        );
    }

    private function getProjectInfo($fullName)
    {
        return $this->projectsCache[$fullName];
    }

    private function getComposerRequests(Client $client, Response $response)
    {
        $requests = [];

        foreach ($response->json() as $repo) {
            if (!$repo['permissions']['admin']) {
                $this->addNonImportableProject($repo['full_name'], 'no admin rights on the project');
                continue;
            }

            $this->cacheProjectInfo($repo);

            $request = $client->get(
                [$repo['contents_url'], ['path' => 'composer.json']],
                [
                    'Accept' => 'application/vnd.github.v3.raw',
                    'X-Full-Name' => $repo['full_name']
                ],
                ['exceptions' => false]
            );

            $requests[] = $request;
        }

        return $requests;
    }

    public function discover(User $user)
    {
        $client = clone $this->client;
        $client->setDefaultOption('headers/Authorization', 'token '.$user->getAccessToken());

        $request = $client->get('/user/orgs');

        $response = $request->send();
        $data = $response->json();

        $orgRequests = [$client->get('/user/repos')];

        foreach ($data as $org) {
            $this->logger->debug(sprintf('adding "'.$org['repos_url'].'" for crawl'));

            $orgRequests[] = $client->get($org['repos_url']);
        }

        $orgResponses = $client->send($orgRequests);

        $composerRequests = [];

        foreach ($orgResponses as $orgResponse) {
            $composerRequests = array_merge($composerRequests, $this->getComposerRequests($client, $orgResponse));

            if ($orgResponse->hasHeader('link')) {
                $link = $orgResponse->getHeader('link');

                if (preg_match('/.* <(.+?)\?page=(\d+)>; rel="last"$/', $link, $matches)) {
                    $pagesRequests = [];

                    for ($i = 2; $i <= $matches[2]; $i++) {
                        $this->logger->debug(sprintf('adding "'.($matches[1].'?page='.$i).'" for crawl'));

                        $pagesRequests[] = $client->get($matches[1].'?page='.$i);
                    }

                    $pagesResponses = $client->send($pagesRequests);

                    foreach ($pagesResponses as $pagesResponse) {
                        $composerRequests = array_merge($composerRequests, $this->getComposerRequests($client, $pagesResponse));
                    }
                }
            }
        }

        $client->send($composerRequests);

        $allowedPackages = ['symfony/symfony', 'laravel/laravel'];

        foreach ($composerRequests as $repoRequest) {
            $fullName = (string) $repoRequest->getHeader('x-full-name');
            $repoResponse = $repoRequest->getResponse();

            if ($repoResponse->getStatusCode() !== 200) {
                $this->addNonImportableProject($fullName, 'no composer.json found');
                continue;
            }

            $data = $repoResponse->json();

            $hasSatisfactoryPackage = false;

            foreach ($allowedPackages as $name) {
                if (isset($data['require']) && isset($data['require'][$name])) {
                    $hasSatisfactoryPackage = true;
                }
            }

            if (!isset($data['require']) || !$hasSatisfactoryPackage) {
                $this->addNonImportableProject($fullName, 'Satisfactory package not found in composer.json');
                continue;
            }

            $this->addImportableProject($fullName);
        }

        return $this->getImportableProjects();
    }
}