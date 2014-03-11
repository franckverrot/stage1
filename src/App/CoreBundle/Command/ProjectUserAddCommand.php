<?php

namespace App\CoreBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

use InvalidArgumentException;

class ProjectUserAddCommand extends ContainerAwareCommand
{
    public function configure()
    {
        $this
            ->setName('stage1:project:user:add')
            ->setDescription('Adds an user to a project')
            ->setDefinition([
                new InputArgument('project', InputArgument::REQUIRED, 'The project\'s id or slug'),
                new InputArgument('user', InputArgument::REQUIRED, 'The user\'s id or username'),
            ]);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $project = $this->findProject($input->getArgument('project'));
        $user = $this->findUser($input->getArgument('user'));

        if ($project->getUsers()->contains($user)) {
            $output->writeln(sprintf('User <info>%s</info> already in project <info>%s</info>', $user->getUsername(), $project->getFullName()));
            return;
        }

        $project->addUser($user);

        $em = $this->getContainer()->get('doctrine')->getManager();
        $em->persist($project);
        $em->flush();

        $output->writeln(sprintf('Added user <info>%s</info> to project <info>%s</info>', $user->getUsername(), $project->getFullName()));

    }

    private function findUser($spec)
    {
        $repository = $this->getContainer()->get('doctrine')->getRepository('Model:User');

        if (is_numeric($spec)) {
            return $repository->find((integer) $spec);
        }

        $users = $repository->findByUsername($spec);

        if (count($users) === 0) {
            throw new InvalidArgumentException('User not found');
        }

        return $users[0];
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