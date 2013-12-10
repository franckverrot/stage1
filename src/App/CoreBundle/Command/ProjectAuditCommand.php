<?php

namespace App\CoreBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Yaml\Yaml;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

use InvalidArgumentException;

class ProjectAuditCommand extends ContainerAwareCommand
{
    public function configure()
    {
        $this
            ->setName('project:audit')
            ->setDescription('Retrieves information about a project')
            ->setDefinition([
                new InputArgument('project', InputArgument::REQUIRED, 'The project\'s id or slug')
            ]);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $project = $input->getArgument('project');

        $infos = [];

        $project = $this->findProject($input->getArgument('project'));

        $infos['name'] = $project->getFullName();
        $infos['private'] = $project->getGithubPrivate();
        $infos['users'] = $project->getUsers()->map(function($user) { return $user->getUsername(); })->toArray();
        $infos['branches'] = $project->getBranches()->map(function($branch) { return $branch->getName(); })->toArray();

        $infos['builds'] = array(
            'total' => count($project->getBuilds()),
            'running' => count($project->getRunningBuilds()),
            'building' => count($project->getBuildingBuilds()),
        );

        $accessToken = $project->getUsers()->first()->getAccessToken();

        $output->writeln('Using access token <info>'.$accessToken.'</info>');

        $client = $this->getContainer()->get('app_core.client.github');
        $client->setDefaultOption('headers/Authorization', 'token '.$accessToken);
        $client->setDefaultOption('headers/Accept', 'application/vnd.github.v3');

        $response = $client->get($project->getKeysUrl())->send();

        $infos['has_deploy_key'] = 'no';        

        foreach ($response->json() as $githubKey) {
            if ($githubKey['key'] === $project->getPublicKey()) {
                $infos['has_deploy_key'] = 'yes';
            }
        }

        $infos['tokens'] = [];

        foreach ($project->getUsers() as $user) {
            $infos['tokens'][$user->getUsername()] = $user->getAccessToken();
        }

        $content = Yaml::dump($infos, 10);
        $content = preg_replace('/^(\s*)([^:\n]+)(:)/m', '\\1<info>\\2</info>\\3', $content);
        $content = preg_replace('/^([^:-]+)(-|:) ([^\n]+)$/m', '\\1\\2 <comment>\\3</comment>', $content);

        $output->writeln($content);
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