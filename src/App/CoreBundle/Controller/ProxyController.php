<?php

namespace App\CoreBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

class ProxyController extends Controller
{
    private function setCorsHeaders(Response $response)
    {
        $response->headers->set('Access-Control-Allow-Methods', 'GET POST OPTIONS');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, *');

        return $response;
    }

    public function issueAction(Request $request)
    {
        $response = new JsonResponse(['foo' => 'bar'], 200);
        $this->setCorsHeaders($response);

        return $response;
    }
}