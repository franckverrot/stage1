<?php

namespace App\CoreBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UserFixCommand extends ContainerAwareCommand
{
    public function configure()
    {
        $this
            ->setName('user:fix')
            ->setDescription('Fixes malformed User entities');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $repository = $this->getContainer()->get('doctrine')->getRepository('AppCoreBundle:User');
        $em = $this->getContainer()->get('doctrine')->getManager();

        $env = $this->getContainer()->getParameter('kernel.environment');

        foreach ($repository->findAll() as $user) {
            if ($env === 'prod' && strlen($user->getChannel(true)) === 0) {
                $output->writeln('generating channel for <info>'.$user->getUsername().'</info>');
                $user->setChannel(uniqid(mt_rand(), true));
            }

            if ($env === 'dev' && strlen($user->getChannel(true)) > 0) {
                $output->writeln('nulling channel for <info>'.$user->getUsername().'</info>');
                $user->setChannel(null);
            }

            $em->persist($user);
        }

        $em->flush();
    }
}