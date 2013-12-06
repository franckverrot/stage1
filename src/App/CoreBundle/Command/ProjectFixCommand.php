<?php

namespace App\CoreBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ProjectFixCommand extends ContainerAwareCommand
{
    public function configure()
    {
        $this
            ->setName('project:fix');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $repository = $this->getContainer()->get('doctrine')->getRepository('AppCoreBundle:Project');
        $em = $this->getContainer()->get('doctrine')->getManager();

        foreach ($repository->findAll() as $project) {
            if (null === $project->getDockerBaseImage()) {
                $output->writeln('fixing base image for <info>'.$project->getGithubFullName().'</info>');
                $project->setDockerBaseImage('symfony2:latest');

                $em->persist($project);
            }
        }

        $em->flush();
    }
}