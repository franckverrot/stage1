<?php

namespace App\CoreBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use App\CoreBundle\Entity\Build;

class BuildCheckTimeoutCommand extends ContainerAwareCommand
{
    public function configure()
    {
        $this
            ->setName('stage1:build:check-timeout')
            ->setDescription('Checks for timeouted builds and fixes them')
            ->setDefinition([
                new InputOption('ttl', 't', InputOption::VALUE_REQUIRED, 'The ttl')
            ]);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $em = $this->getContainer()->get('doctrine')->getManager();
        $rp = $em->getRepository('AppCoreBundle:Build');

        if (null === $input->getOption('ttl')) {
            $ttl = $this->getContainer()->getParameter('stage1_build_timeout');
        } else {
            $ttl = $input->getOption('ttl');
        }

        $builds = $rp->findTimeouted($ttl);

        foreach ($builds as $build) {
            $output->writeln('marking build #<info>'.$build->getId().'</info> as timeouted');
            $build->setStatus(Build::STATUS_FAILED);
            $em->persist($build);
        }

        $em->flush();
    }
}