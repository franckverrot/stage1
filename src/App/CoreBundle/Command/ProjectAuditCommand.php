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

        $infos = array(
            'name' => null,
            'count' => 1,
        );

        if (is_numeric($project)) {
            $project = $repository->find((integer) $project);
        } else {
            $projects = $repository->findBySlug($project);

            if (count($projects) > 0) {
                $project = $projects[0];
            }

            $infos['count'] = count($projects);
        }

        if (!is_object($project)) {
            $output->writeln('<error>Project not found</error>');
            return 1;
        }

        $infos['name'] = $project->getFullName();

        $max = max(array_map('strlen', array_keys($infos)));

        foreach ($infos as $key => $value) {
            $output->writeln(sprintf('<comment>%- '.($max + 3).'s</comment> %s', $key, $value));
        }
    }
}