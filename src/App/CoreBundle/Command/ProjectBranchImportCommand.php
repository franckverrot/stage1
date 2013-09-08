<?php

namespace App\CoreBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

use App\CoreBundle\Entity\Branch;

use DateTime;

class ProjectBranchImportCommand extends ContainerAwareCommand
{
    public function configure()
    {
        $this
            ->setName('project:branch:import')
            ->setDefinition([
                new InputArgument('project_spec', InputArgument::REQUIRED, 'The project\'s spec'),
            ]);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $project = $this->findProject($input->getArgument('project_spec'));
        $branches = $project->getBranches()->map(function($branch) { return $branch->getName(); })->toArray();

        $client = $this->getContainer()->get('app_core.client.github');
        $client->setDefaultOption('headers/Authorization', 'token '.$project->getUsers()->first()->getAccessToken());

        $request = $client->get(['/repos/{owner}/{repo}/branches', [
            'owner' => $project->getGithubOwnerLogin(),
            'repo' => $project->getName(),
        ]]);

        $response = $request->send();

        foreach ($response->json() as $data) {

            if (array_search($data['name'], $branches) !== false) {
                $output->writeln('skipping existing branch <info>'.$data['name'].'</info>');
                continue;
            }

            $output->writeln('importing branch <info>'.$data['name'].'</info>');

            $branch = new Branch();
            $branch->setName($data['name']);

            $now = new DateTime();

            $branch->setCreatedAt($now);
            $branch->setUpdatedAt($now);
            
            $branch->setProject($project);
            $project->addBranch($branch);

            $builds = $this
                ->getContainer()
                ->get('doctrine')
                ->getRepository('AppCoreBundle:Build')
                ->findByRef($branch->getName());

            foreach ($builds as $build) {
                $branch->addBuild($build);
                $build->setBranch($branch);
            }

            if (count($builds) > 0) {
                $output->writeln('  - associated <info>'.count($builds).'</info> builds');
            }
        }

        $em = $this->getContainer()->get('doctrine')->getManager();
        $em->persist($project);
        $em->flush();
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