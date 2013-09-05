<?php

namespace App\CoreBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

class UserListCommand extends ContainerAwareCommand
{
    public function configure()
    {
        $this
            ->setName('user:list')
            ->setDescription('Retrieves a list of users');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $repository = $this->getContainer()->get('doctrine')->getRepository('AppCoreBundle:User');

        foreach ($repository->findAll() as $user) {
            $output->writeln(sprintf('<info>%- 4d</info> %s', $user->getId(), $user->getUsername()));
        }
   }
}