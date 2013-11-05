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
        return $this->render('AppAdminBundle:Docker/Container:top.html.twig', [
            'top' => $this->fetch(['/containers/{id}/top', ['id' => $id]]),
            'container' => $this->fetch(['/containers/{id}/json', ['id' => $id]]),
        ]);
    }
}