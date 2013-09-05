<?php

namespace App\CoreBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

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
        $repository = $this->getContainer()->get('doctrine')->getRepository('AppCoreBundle:Project');

        $infos = [];

        $project = $this->findProject($input->getArgument('project'));

        $infos['name'] = $project->getFullName();
        $infos['users'] = $project->getUsers()->map(function($user) { return $user->getUsername(); })->toArray();
        $infos['builds'] = array(
            'total' => count($project->getBuilds()),
            'running' => count($project->getRunningBuilds()),
            'building' => count($project->getBuildingBuilds()),
        );

        foreach ($infos as $key => $value) {
            if (is_string($value)) {
                $output->writeln(sprintf('<info>%s</info>: <comment>%s</comment>', $key, $value));
            } elseif (is_array($value)) {
                $output->writeln(sprintf('<info>%s</info>:', $key));

                if (is_numeric(key($value))) {
                    foreach ($value as $line) {
                        $output->writeln('  - <comment>'.$line.'</comment>');
                    }                    
                } else {
                    $m = max(array_map('strlen', array_keys($value)));

                    foreach ($value as $k => $v) {
                        $output->writeln(sprintf('  <info>%s</info>: <comment>%s</comment>', $k, $v));
                    }
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