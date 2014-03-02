<?php

namespace App\CoreBundle\Command;

use App\CoreBundle\Entity\Project;
use App\CoreBundle\Entity\Organization;
use App\CoreBundle\SshKeys;

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
            $githubInfos = $this->getGithubInfos($project);

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
                $project->setGithubPrivate($githubInfos['private']);
            }

            if (strlen($project->getContentsUrl()) === 0) {
                $output->writeln('fixing github contents url for <info>'.$project->getGithubFullName().'</info>');
                if (!isset($githubInfos['contents_url'])) {
                    $output->writeln('<error>could not find a <info>contents_url</info> for <info>'.$project->getGithubFullName().'</info></error>');
                }
                
                $project->setContentsUrl($githubInfos['contents_url']);
            }

            if (null === $project->getOrganization() && isset($githubInfos['organization'])) {
                $output->writeln('fixing organization for <info>'.$project->getGithubFullName().'</info>');
                $orgKeys = SshKeys::generate();
                $org = new Organization();
                $org->setName($githubInfos['organization']['login']);
                $org->setGithubId($githubInfos['organization']['id']);
                $org->setPublicKey($orgKeys['public']);
                $org->setPrivateKey($orgKeys['private']);

                $project->setOrganization($org);
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