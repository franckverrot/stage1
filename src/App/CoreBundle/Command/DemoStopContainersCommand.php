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
                new InputOption('force', null, InputOption::VALUE_NONE, 'Use the force'),
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

        $docker = $this->getContainer()->get('app_core.docker');
        $em = $this->getContainer()->get('doctrine')->getManager();

        $force = $input->getOption('force');

        foreach ($builds as $build) {
            $output->writeln('stopping container <info>'.substr($build->getContainerId(), 0, 8).'</info>');

            if (!$force) {
                continue;
            }

            try {
                $container = $build->getContainer();

                $docker->getContainerManager()
                    ->stop($container)
                    ->remove($container);

                $build->setStatus(Build::STATUS_STOPPED);
                $em->persist($build);
            } catch (\Exception $e) {
                $output->writeln('  <error>could not stop container</error>');
                $output->writeln(sprintf('  [%s] %s', get_class($e), $e->getMessage()));
            }

        }

        if ($force) {
            $em->flush();
        } else {
            $output->writeln('<error>Use the --force.</error>');
        }
    }
}