<?php

namespace App\CoreBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Console\Helper\TableHelper;

class ContainerStopOrphansCommand extends ContainerAwareCommand
{
    public function configure()
    {
        $this
            ->setName('stage1:container:stop-orphans')
            ->setDescription('Stops orphan containers')
            ->setDefinition([
                new InputOption('dry-run', 'd', InputOption::VALUE_NONE, 'Dry-run'),
            ]);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $docker = $this->getContainer()->get('app_core.docker');

        $containers = $docker
            ->getContainerManager()
            ->findAll();

        if (count($containers) === 0) {
            return;
        }

        $em = $this->getContainer()->get('doctrine')->getManager();
        $rp = $em->getRepository('Model:Build');

        foreach ($containers as $container) {
            $build = $rp->findOneByContainerId($container->getId());

            if ($build) {
                continue;
            }

            $output->writeln('Stopping orphan container <info>'.$container->getId().'</info> (<comment>'.$container->getImage()->getRepository().'</comment>)');

            if (!$input->getOption('dry-run')) {
                $docker->getContainerManager()->stop($container);
            }
        }
    }
}