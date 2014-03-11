<?php

namespace App\CoreBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

use Doctrine\ORM\NoResultException;

class BuildPreviousCommand extends ContainerAwareCommand
{
    public function configure()
    {
        $this
            ->setName('stage1:build:previous')
            ->setDescription('Finds a previous build for a specific build')
            ->setDefinition([
                new InputArgument('build_id', InputArgument::REQUIRED, 'The build id'),
            ]);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $repo = $this->getContainer()->get('doctrine')->getRepository('Model:Build');

        $build = $repo->find($input->getArgument('build_id'));

        if (!$build) {
            return;
        }

        $output->writeln('Current build:');
        $output->writeln('  Project: <info>'.$build->getProject()->getFullName().'</info>');
        $output->writeln('  Ref: <info>'.$build->getRef().'</info>');

        try {
            $previousBuild = $repo->findPreviousBuild($build);
        } catch (NoResultException $e) {
            $output->writeln('No previous build found.');
            return;
        }

        $output->writeln('Found previous build <info>#'.$previousBuild->getId().'</info>');
    }
}