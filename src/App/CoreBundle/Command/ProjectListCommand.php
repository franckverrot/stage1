<?php

namespace App\CoreBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

class ProjectListCommand extends ContainerAwareCommand
{
    public function configure()
    {
        $this
            ->setName('project:list')
            ->setDescription('Retrieves a list of projects');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $repository = $this->getContainer()->get('doctrine')->getRepository('AppCoreBundle:Project');

        foreach ($repository->findAll() as $project) {
            $output->writeln(sprintf('<info>%- 4d</info> %s', $project->getId(), $project->getSlug()));
        }
    }
}