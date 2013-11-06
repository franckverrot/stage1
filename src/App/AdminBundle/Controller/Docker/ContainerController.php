<?php

namespace App\AdminBundle\Controller\Docker;

use App\AdminBundle\Controller\Controller;

use Guzzle\Http\Client;

class ContainerController extends Controller
{
    private function getClient()
    {
        return $this->container->get('app_core.client.docker');
    }

    private function fetch($urlspec)
    {
        return $this->getClient()->get($urlspec)->send()->json();
    }

    public function indexAction()
    {
        return $this->render('AppAdminBundle:Docker/Container:index.html.twig', [
            'containers' => $this->fetch('/containers/json'),
        ]);
    }

    public function inspectAction($id)
    {
        return $this->render('AppAdminBundle:Docker/Container:inspect.html.twig', [
            'container' => $this->fetch(['/containers/{id}/json', ['id' => $id]]),
        ]);
    }

    public function topAction($id)
    {
        $top = $this->fetch(['/containers/{id}/top?{query*}', ['id' => $id, 'query' => ['ps_args' => 'aux']]]);

        foreach ($top['Processes'] as &$entry) {
            $entry[10] = implode(' ', array_splice($entry, 10));
        }

        return $this->render('AppAdminBundle:Docker/Container:top.html.twig', [
            'top' => $top,
            'container' => $this->fetch(['/containers/{id}/json', ['id' => $id]]),
        ]);
    }
}