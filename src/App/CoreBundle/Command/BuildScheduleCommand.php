<?php

namespace App\CoreBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use App\CoreBundle\Entity\Build;
use App\CoreBundle\Entity\Project;

use InvalidArgumentException;

class BuildScheduleCommand extends ContainerAwareCommand
{
    public function configure()
    {
        $this
            ->setName('build:schedule')
            ->setDefinition([
                new InputArgument('project_spec', InputArgument::REQUIRED, 'The project'),
                new InputArgument('ref', InputArgument::REQUIRED, 'The ref'),
            ]);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $em = $this->getContainer()->get('doctrine')->getManager();

        $project = $em
            ->getRepository('AppCoreBundle:Project')
            ->findOneBySpec($input->getArgument('project_spec'));

        if (!$project) {
            throw new InvalidArgumentException('project not found');
        }

        $ref = $input->getArgument('ref');
        // $hash = $this->getHashFromRef($project, $ref);
        $hash = null;

        $build = $this
            ->getContainer()
            ->get('app_core.build_scheduler')
            ->schedule($project, $ref, $hash);

        $output->writeln('scheduled build <info>'.$build->getId().'</info>');
    }

    protected function getHashFromRef(Project $project, $ref)
    {
        $accessToken = $project->getUsers()->first()->getAccessToken();

        $this->getContainer()->get('logger')->info('using access token '.$accessToken);

        $client = $this->getContainer()->get('app_core.client.github');
        $client->setDefaultOption('headers/Authorization', 'token '.$accessToken);
        $client->setDefaultOption('headers/Accept', 'application/vnd.github.v3');

        $request = $client->get(['/repos/{owner}/{repo}/git/refs/heads', [
            'owner' => $project->getGithubOwnerLogin(),
            'repo' => $project->getName(),
        ]]);

        $response = $request->send();
        $remoteRefs = $response->json();

        foreach ($remoteRefs as $remoteRef) {
            if ('refs/heads/'.$ref === $remoteRef['ref']) {
                $hash = $remoteRef['object']['sha'];
                break;
            }
        }

        return $hash;
    }
}