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
            ->setName('project:containers:stop')
            ->setDefinition([
                new InputArgument('project_spec', InputArgument::REQUIRED, 'The project\'s spec'),
            ]);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $project = $this->findProject($input->getArgument('project_spec'));

        $client = $this->getContainer()->get('app_core.client.docker');
        $request = $client->get('/containers/json');
        $response = $request->send();

        $prefix = 'b/'.$project->getId();
        $prefixLength = strlen($prefix);

        $count = 0;

        foreach ($response->json() as $container) {
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