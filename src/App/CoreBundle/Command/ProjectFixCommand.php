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
            ->setName('stage1:project:fix')
            ->setDescription('Fixes malformed Project entities');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $repository = $this->getContainer()->get('doctrine')->getRepository('AppCoreBundle:Project');
        $em = $this->getContainer()->get('doctrine')->getManager();

        foreach ($repository->findAll() as $project) {
            if (null === $project->getDockerBaseImage()) {
                $output->writeln('fixing base image for <info>'.$project->getGithubFullName().'</info>');
                $project->setDockerBaseImage('symfony2:latest');
            }

            if (null === $project->getGithubUrl()) {
                $output->writeln('fixing github url for <info>'.$project->getGithubFullName().'</info>');
                $project->setGithubUrl('https://api.github.com/repos/'.$project->getGithubFullName());
            }

            if (null === $project->getGithubPrivate()) {
                $output->writeln('fixing github private status for <info>'.$project->getGithubFullName().'</info>');
                $client = $this->getContainer()->get('app_core.client.github');
                $client->setDefaultOption('headers/Authorization', 'token '.$project->getUsers()->first()->getAccessToken());

                $request = $client->get($project->getGithubUrl());
                $response = $request->send();

                $data = $response->json();

                $project->setGithubPrivate($data['private']);
            }

            $em->persist($project);
        }

        $em->flush();
    }
}