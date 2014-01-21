<?php

namespace App\CoreBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

use Symfony\Component\Yaml\Yaml;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use App\CoreBundle\Entity\Build;

use DateTime;

class DemoStopContainersCommand extends ContainerAwareCommand
{
    public function configure()
    {
        $this
            ->setName('stage1:demo:stop-containers')
            ->setDescription('Stops all demo containers')
            ->setDefinition([
                new InputOption('ttl', 't', InputOption::VALUE_REQUIRED, 'The ttl of containers', 0),
                new InputOption('dry-run', null, InputOption::VALUE_NONE, 'Dry run'),
            ]);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $config = Yaml::parse($this->getContainer()->getParameter('kernel.root_dir').'/config/demo.yml');

        if (0 === $ttl = $input->getOption('ttl')) {
            $ttl = strtotime($config['default_ttl'], 0);
        }

        $queryBuilder = $this->getContainer()->get('doctrine')->getRepository('AppCoreBundle:Build')->createQueryBuilder('b');

        $builds = $queryBuilder
            ->leftJoin('b.project', 'p')
            ->leftJoin('p.users', 'u')
            ->where('u.username = ?1')
            ->andWhere('b.status = ?2')
            ->andWhere('b.createdAt <= ?3')
            ->setParameters([
                1 => $config['username'],
                2 => Build::STATUS_RUNNING,
                3 => new DateTime(strtotime(-$ttl))
            ])
            ->getQuery()
            ->execute();

        $client = $this->getContainer()->get('app_core.client.docker');
        $em = $this->getContainer()->get('doctrine')->getManager();

        $dryRun = $input->getOption('dry-run');

        foreach ($builds as $build) {
            if ($dryRun) {
                $output->writeln('would stop container <info>'.$build->getContainerId().'</info>');
            } else {
                $request = $client->post(['/containers/{id}/stop', ['id' => $build->getContainerId()]]);
                $response = $request->send();

                if ($response->getStatusCode() !== 204) {
                    $output->writeln('error stopping container <info>'.$build->getContainerId().'</info>');
                    $output->writeln(json_encode($response->json(), JSON_PRETTY_PRINT));
                }

                $build->setStatus(Build::STATUS_STOPPED);
                $em->persist($build);                
            }
        }

        $em->flush();
    }
}