<?php

namespace App\CoreBundle\Discover;

use Guzzle\Http\Client;
use App\CoreBundle\Entity\User;

class Github
{
    private $client;

    private $projects;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    private function cacheProjectInfo($data)
    {
        $this->projects[$data['full_name']] = array(
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
        return $this->projects[$fullName];
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
            $orgRequests[] = $client->get($org['repos_url']);
        }

        $orgResponses = $client->send($orgRequests);

        $repoRequests = array();

        foreach ($orgResponses as $orgResponse) {
            $data = $orgResponse->json();

            foreach ($data as $repo) {
                if (!$repo['permissions']['admin']) {
                    continue;
                }

                $this->cacheProjectInfo($repo);

                $repoRequests[] = $client->get(
                    [$repo['contents_url'], ['path' => 'composer.json']],
                    [
                        'Accept' => 'application/vnd.github.VERSION.raw',
                        'X-Full-Name' => $repo['full_name']
                    ],
                    ['exceptions' => false]
                );
            }
        }

        $repoResponses = $client->send($repoRequests);

        foreach ($repoRequests as $repoRequest) {
            $repoResponse = $repoRequest->getResponse();
            $fullName = (string) $repoRequest->getHeader('x-full-name');

            if ($repoResponse->getStatusCode() !== 200) {
                continue;
            }

            $data = $repoResponse->json();

            if (!isset($data['require']) || !isset($data['require']['symfony/symfony'])) {
                continue;
            }

            $projects[$fullName] = $this->getProjectInfo($fullName);
        }

        return $projects;
    }
}