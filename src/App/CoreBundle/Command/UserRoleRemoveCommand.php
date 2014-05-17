<?php

namespace App\CoreBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

class UserRoleRemoveCommand extends ContainerAwareCommand
{
    public function configure()
    {
        $this
            ->setName('stage1:user:role:remove')
            ->setDescription('Remove a user\'s role')
            ->setDefinition([
                new InputArgument('user_spec', InputArgument::REQUIRED, 'The user'),
                new InputArgument('role', InputArgument::REQUIRED, 'The role'),
            ]);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $user = $this->findUser($input->getArgument('user_spec'));

        $output->writeln('removing role <info>'.$input->getArgument('role').'</info> for user <info>'.$user->getUsername().'</info>');

        $user->removeRole($input->getArgument('role'));

        $em = $this->getContainer()->get('doctrine')->getManager();
        $em->persist($user);
        $em->flush();
    }

    private function findUser($spec)
    {
        $repository = $this->getContainer()->get('doctrine')->getRepository('Model:User');

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