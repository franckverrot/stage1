<?php

namespace App\CoreBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

use Exception;
use RuntimeException;

class ProxyController extends Controller
{
    private function setCorsHeaders(Response $response)
    {
        $this->get('logger')->debug('adding CORS headers');

        $response->headers->set('Access-Control-Allow-Methods', 'GET POST OPTIONS');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, *');

        return $response;
    }

    public function issuesAction(Request $request)
    {
        if ($request->isMethod('options')) {
            $response = new Response(null, 200);
            $this->setCorsHeaders($response);

            return $response;
        }

        try {
            $logger = $this->get('logger');

            $projectName = $request->request->get('stage1-project');

            $logger->info('looking project up', ['project_name' => $projectName]);

            $project = $this
                ->get('doctrine')
                ->getRepository('Model:Project')
                ->findOneByGithubFullName($projectName);

            if (null === $project) {
                throw new RuntimeException(sprintf('Could not find project "%s"', $projectName));
            }

            $logger->info('found project', ['project_name' => $projectName, 'project' => $project->getId()]);

            $accessToken = $project->getUsers()->first()->getAccessToken();

            $logger->info('using access token', ['access_token' => $accessToken]);

            $github = $this->get('app_core.client.github');

            $github->setDefaultOption('headers/Accept', 'application/vnd.github.v3');
            $github->setDefaultOption('headers/Authorization', 'token '.$accessToken);

            $githubRequest = $github->post('/repos/'.$projectName.'/issues');
            $githubRequest->setBody(json_encode([
                'title' => $request->request->get('stage1-issue-title'),
                'body' => $request->request->get('stage1-issue-body'),
                'labels' => ['stage1']
            ]));

            $logger->info('sending github request', [
                'method' => $githubRequest->getMethod(),
                'url' => $githubRequest->getUrl(),
                'body' => (string) $githubRequest->getBody(),
                'access_token' => $accessToken
            ]);

            $githubResponse = $github->send($githubRequest);

            $logger->info('got github response');

            $response = new JsonResponse([
                'github_response' => $githubResponse->json()
            ], $githubResponse->getStatusCode());            
        } catch (Exception $e) {
            $response = new JsonResponse([
                'exception' => get_class($e),
                'message' => $e->getMessage()
            ], 500);
        }

        $this->setCorsHeaders($response);

        return $response;
    }
}