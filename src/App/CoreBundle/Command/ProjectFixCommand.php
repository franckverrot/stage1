<?php

namespace App\CoreBundle\Command;

use App\CoreBundle\Entity\Project;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ProjectFixCommand extends ContainerAwareCommand
{
    private $githubInfos = [];

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
            if (strlen($project->getDockerBaseImage()) === 0) {
                $output->writeln('fixing base image for <info>'.$project->getGithubFullName().'</info>');
                $project->setDockerBaseImage('symfony2:latest');
            }

            if (strlen($project->getGithubUrl()) === 0) {
                $output->writeln('fixing github url for <info>'.$project->getGithubFullName().'</info>');
                $project->setGithubUrl('https://api.github.com/repos/'.$project->getGithubFullName());
            }

            if (null === $project->getGithubPrivate()) {
                $output->writeln('fixing github private status for <info>'.$project->getGithubFullName().'</info>');
                $project->setGithubPrivate($this->getGithubInfos($project)['private']);
            }

            if (strlen($project->getContentsUrl()) === 0) {
                $output->writeln('fixing github contents url for <info>'.$project->getGithubFullName().'</info>');
                $project->setContentsUrl($this->getGithubInfos($project)['contents_url']);
            }

            $em->persist($project);
        }

        $em->flush();
    }

    private function getGithubInfos(Project $project)
    {
        if (!array_key_exists($project->getGithubFullName(), $this->githubInfos)) {
            $client = $this->getContainer()->get('app_core.client.github');
            $client->setDefaultOption('headers/Authorization', 'token '.$project->getUsers()->first()->getAccessToken());
            $client->setDefaultOption('headers/Accept', 'application/vnd.github.v3');
            $request = $client->get($project->getGithubUrl());
            $response = $request->send();

            $this->githubInfos[$project->getGithubFullName()] = $response->json();
        }

        return $this->githubInfos[$project->getGithubFullName()];
    }
}