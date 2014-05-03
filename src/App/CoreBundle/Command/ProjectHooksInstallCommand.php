<?php

namespace App\CoreBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

use InvalidArgumentException;

class ProjectHooksInstallCommand extends ContainerAwareCommand
{
    public function configure()
    {
        $this
            ->setName('stage1:project:hooks:install')
            ->setDescription('Reinstalls a project\'s hooks')
            ->setDefinition([
                new InputArgument('project_spec', InputArgument::REQUIRED, 'The project\'s spec'),
            ]);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $project = $this->findProject($input->getArgument('project_spec'));

        $output->writeln('installing hooks for project <info>'.$project->getGithubFullName().'</info>');

        if ($project->isDemo()) {
            $config = Yaml::parse($this->getContainer()->getParameter('kernel.root_dir').'/config/demo.yml');
            $accessToken = $config['access_token'];
        } else {
            $accessToken = $project->getUsers()->first()->getAccessToken();
        }

        $output->writeln('using access token <info>'.$accessToken.'</info>');

        $client = $this->getContainer()->get('app_core.client.github');
        $client->setDefaultOption('headers/Authorization', 'token '.$accessToken);
        $client->setDefaultOption('headers/Accept', 'application/vnd.github.v3');

        $request = $client->get($project->getHooksUrl());
        $response = $request->send();

        $hooks = $response->json();

        foreach ($hooks as $hook) {
            if ($hook['name'] === 'web' && strpos($hook['config']['url'], 'stage1.io') !== false) {
                $request = $client->delete($hook['url']);
                $request->send();
            }
        }

        $router = $this->getContainer()->get('router');
        $githubHookUrl = $router->generate('app_core_hooks_github', [], true);
        $githubHookUrl = str_replace('http://localhost', 'http://stage1.io', $githubHookUrl);

        $request = $client->post($project->getHooksUrl());
        $request->setBody(json_encode([
            'name' => 'web',
            'active' => true,
            'events' => ['push', 'pull_request'],
            'config' => ['url' => $githubHookUrl, 'content_type' => 'json'],
        ]), 'application/json');

        $response = $request->send();
        $installedHook = $response->json();

        $project->setGithubHookId($installedHook['id']);

        $em = $this->getContainer()->get('doctrine')->getManager();
        $em->persist($project);
        $em->flush();
    }

    private function findProject($spec)
    {
        $repository = $this->getContainer()->get('doctrine')->getRepository('Model:Project');

        if (is_numeric($spec)) {
            return $repository->find((integer) $spec);
        }

        $projects = $repository->findBySlug($spec);

        if (count($projects) === 0) {
            throw new InvalidArgumentException('Project not found');
        }

        return $projects[0];
    }
}