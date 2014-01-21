<?php

namespace App\CoreBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;

use InvalidArgumentException;

class ProjectContainersStopCommand extends ContainerAwareCommand
{
    public function configure()
    {
        $this
            ->setName('stage1:project:containers:stop')
            ->setDescription('Stops all containers for a project')
            ->setDefinition([
                new InputArgument('project_spec', InputArgument::REQUIRED, 'The project\'s spec'),
            ]);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $project = $this->findProject($input->getArgument('project_spec'));

        $output->writeln('found project <info>'.$project->getGithubFullName().'</info>');

        $client = $this->getContainer()->get('app_core.docker.http_client');
        $request = $client->get('/containers/json');
        $response = $client->send($request);

        $prefix = 'b/'.$project->getId();
        $prefixLength = strlen($prefix);

        $containers = $response->json(true);

        $output->writeln('found <info>'.count($containers).'</info> running containers');

        $count = 0;

        foreach ($response->json(true) as $container) {
            if (substr($container['Image'], 0, $prefixLength) === $prefix) {
                $output->writeln('stopping container <info>'.$container['Id'].'</info>');
                $request = $client->post(['/containers/{id}/stop', ['id' => $container['Id']]]);
                $request->send();

                $count++;
            }
        }

        $output->write(PHP_EOL);
        $output->writeln('stopped <info>'.$count.'</info> containers');
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