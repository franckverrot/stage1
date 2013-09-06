<?php

namespace App\CoreBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

use App\CoreBundle\Entity\User;

use Guzzle\Http\Client;
use Guzzle\Http\Exception\MultiTransferException;

class UserProjectDiscoverCommand extends ContainerAwareCommand
{
    private $projects = [];

    public function configure()
    {
        $this
            ->setName('user:project:discover')
            ->setDescription('Discovers a user\'s projects')
            ->setDefinition([
                new InputArgument('user_spec', InputArgument::REQUIRED, 'The user')
            ]);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $user = $this->findUser($input->getArgument('user_spec'));

        $output->writeln('using access token <info>'.$user->getAccessToken().'</info>');

        $discover = $this->getContainer()->get('app_core.discover.github');
        $projects = $discover->discover($user);

        foreach ($projects as $fullName => $project) {
            $output->writeln('found project <info>'.$fullName.'</info>');
        }
    }

    private function findUser($spec)
    {
        $repository = $this->getContainer()->get('doctrine')->getRepository('AppCoreBundle:User');

        if (is_numeric($spec)) {
            return $repository->find((integer) $spec);
        }

        $users = $repository->findByUsername($spec);

        if (count($users) === 0) {
            throw new InvalidArgumentException('User not found');
        }

        return $users[0];
    }
}