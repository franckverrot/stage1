<?php

namespace App\CoreBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Console\Input\InputArgument;

class ProjectImportCommand extends ContainerAwareCommand
{
    public function configure()
    {
        $this
            ->setName('project:import')
            ->setDefinition([
                new InputArgument('project_full_name', InputArgument::REQUIRED, 'The project\'s full name'),
                new InputArgument('user_spec', InputArgument::REQUIRED, 'The user spec'),
            ]);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $user = $this->findUser($input->getArgument('user_spec'));

        $importer = $this->getContainer()->get('app_core.github.import');
        $importer->setUser($user);

        $project = $importer->import($input->getArgument('project_full_name'), function($step) use ($output) {
            $output->writeln('  - '.$step['label'].' ('.$step['id'].')');
        });

        $output->writeln('Imported project <info>'.$project->getFullName().'</info> (id #<info>'.$project->getId().'</info>)');
    }

    private function findUser($spec)
    {
        $repository = $this->getContainer()->get('doctrine')->getRepository('AppCoreBundle:User');

        if (is_numeric($spec)) {
            return $repository->find((integer) $spec);
        }

        $users = $repository->findByUsername($spec);

        if (count($users) === 0) {
            throw new InvalidArgumentException('User not found');
        }

        return $users[0];
    }
}