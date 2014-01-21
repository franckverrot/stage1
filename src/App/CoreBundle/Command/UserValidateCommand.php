<?php

namespace App\CoreBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

use App\CoreBundle\Entity\User;

class UserValidateCommand extends ContainerAwareCommand
{
    public function configure()
    {
        $this
            ->setName('stage1:user:validate')
            ->setDescription('Validates an otherwise unvalidated user')
            ->setDefinition([
                new InputArgument('user_spec', InputArgument::REQUIRED, 'The user'),
            ]);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $user = $this->findUser($input->getArgument('user_spec'));

        $output->writeln('validating user <info>'.$user->getUsername().'</info>');

        $user->setStatus(User::STATUS_ENABLED);

        $em = $this->getContainer()->get('doctrine')->getManager();
        $em->persist($user);
        $em->flush();
    }

    private function findUser($spec)
    {
        $repository = $this->getContainer()->get('doctrine')->getRepository('AppCoreBundle:User');

        if (is_numeric($spec)) {
            return $repository->find((integer) $spec);
        }

        $user = $repository->findOneByUsername($spec);

        if (!$user) {
            throw new InvalidArgumentException('User not found');
        }

        return $user;
    }
}