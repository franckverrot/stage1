<?php

namespace App\CoreBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

use App\CoreBundle\SshKeys;

use InvalidArgumentException;

class BuildSetStatusCommand extends ContainerAwareCommand
{
    public function configure()
    {
        $this
            ->setName('stage1:build:set-status')
            ->setDescription('Sets a build status')
            ->setDefinition([
                new InputArgument('build_id', InputArgument::REQUIRED, 'The build id'),
                new InputArgument('status', InputArgument::REQUIRED, 'The status label'),
            ]);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $em = $this->getContainer()->get('doctrine')->getManager();
        $build = $em->getRepository('AppCoreBundle:Build')->find($input->getArgument('build_id'));

        if (!$build) {
            throw new InvalidArgumentException('Could not find build');
        }

        $build->setStatus(constant('Build::STATUS_'.strtoupper($input->getArgument('status'))));

        $em->persist($build);
        $em->flush();
    }
}