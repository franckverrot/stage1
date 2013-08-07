<?php

namespace App\CoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\HttpFoundation\Request;

class ConfigureController extends Controller
{
    private function getConfigPath($file)
    {
        return realpath(__DIR__.'/../../../../app/config/'.$file.'.yml');
    }

    public function captureAction()
    {
        return $this->redirect($this->generateUrl('app_core_configure'));
    }

    public function indexAction()
    {
        $yaml = Yaml::parse($this->getConfigPath('github'));

        return $this->render('AppCoreBundle:Configure:index.html.twig', [
            'config' => $yaml['parameters'],
        ]);
    }

    public function saveAction(Request $request)
    {
        $config = $request->request->get('github');

        $yaml = Yaml::parse($this->getConfigPath('github'));
        $yaml['parameters']['github_client_id'] = $config['client_id'];
        $yaml['parameters']['github_client_secret'] = $config['client_secret'];
        $yaml['parameters']['github_base_url'] = $config['base_url'];
        $yaml['parameters']['github_api_base_url'] = $config['api_base_url'];

        file_put_contents($this->getConfigPath('github'), Yaml::dump($yaml));

        $yaml = Yaml::parse($this->getConfigPath('parameters'));
        $yaml['parameters']['configured'] = true;

        file_put_contents($this->getConfigPath('parameters'), Yaml::dump($yaml));

        return $this->render('AppCoreBundle:Configure:success.html.twig');
    }
}