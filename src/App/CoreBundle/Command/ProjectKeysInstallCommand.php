<?php

namespace App\CoreBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

use InvalidArgumentException;

class ProjectKeysInstallCommand extends ContainerAwareCommand
{
    public function configure()
    {
        $this
            ->setName('project:keys:install')
            ->setDescription('Install or reinstalls a project\'s keys')
            ->setDefinition([
                new InputArgument('project_spec', InputArgument::REQUIRED, 'The project\'s spec'),
                new InputOption('delete', 'd', InputOption::VALUE_NONE, 'Delete other existing keys'),
            ]);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $project = $this->findProject($input->getArgument('project_spec'));

        if ($project->isDemo()) {
            $config = Yaml::parse($this->getContainer()->getParameter('kernel.root_dir').'/config/demo.yml');
            $accessToken = $config['access_token'];
        } else {
            $accessToken = $project->getUsers()->first()->getAccessToken();
        }

        $output->writeln('using access token <info>'.$accessToken.'</info>');

        $client = $this->getContainer()->get('app_core.client.github');
        $client->setDefaultOption('headers/Authorization', 'token '.$accessToken);

        $request = $client->get($project->getKeysUrl());
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
            $output->writeln('installing non-existent key');
            $request = $client->post($project->getKeysUrl());
            $request->setBody(json_encode([
                'key' => $projectDeployKey,
                'title' => 'stage1.io (added by support@stage1.io)',
            ]), 'application/json');

            $response = $request->send();
            $installedKey = $response->json();
        } else {
            $output->writeln('key already installed');
        }

        $project->setGithubDeployKeyId($installedKey['id']);

        $em = $this->getContainer()->get('doctrine')->getManager();
        $em->persist($project);
        $em->flush();

        if ($input->getOption('delete') && count($scheduleDelete) > 0) {
            if (count($scheduleDelete) > 0) {
                foreach ($scheduleDelete as $key) {
                    $request = $client->delete([$project->getKeysUrl(), ['key_id' => $key['id']]]);
                    $response = $request->send();
                }
            }
        }
    }

    private function findProject($spec)
    {
        $repository = $this->getContainer()->get('doctrine')->getRepository('AppCoreBundle:Project');

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