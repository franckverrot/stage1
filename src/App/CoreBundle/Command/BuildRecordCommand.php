<?php

namespace App\CoreBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BuildRecordCommand extends ContainerAwareCommand
{
    public function configure()
    {
        $this
            ->setName('build:record')
            ->setDefinition([
                new InputArgument('build_id', InputArgument::REQUIRED, 'The build id'),
            ]);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $build = $this
            ->getContainer()
            ->get('doctrine')
            ->getRepository('AppCoreBundle:Build')
            ->find($input->getArgument('build_id'));

        if (!$build) {
            return;
        }

        $build->setOutput(stream_get_contents(STDIN));

        $em = $this->getContainer()->get('doctrine')->getManager();
        $em->persist($build);
        $em->flush();
    }
}