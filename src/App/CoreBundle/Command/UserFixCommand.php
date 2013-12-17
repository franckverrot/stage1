<?php

namespace App\CoreBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Exception;

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
        $client = $this->getContainer()->get('app_core.client.github');
        $client->setDefaultOption('headers/Accept', 'application/vnd.github.v3');

        foreach ($repository->findAll() as $user) {
            $client->setDefaultOption('headers/Authorization', 'token '.$user->getAccessToken());

            if ($env === 'prod' && strlen($user->getChannel(true)) === 0) {
                $output->writeln('generating channel for <info>'.$user->getUsername().'</info>');
                $user->setChannel(uniqid(mt_rand(), true));
            }

            if ($env === 'dev' && strlen($user->getChannel(true)) > 0) {
                $output->writeln('nulling channel for <info>'.$user->getUsername().'</info>');
                $user->setChannel(null);
            }

            if (strlen($user->getEmail()) === 0) {
                $output->write('fixing email for <info>'.$user->getUsername().'</info> ');

                try {
                    $request = $client->get('/user/emails');
                    $response = $request->send();

                    foreach ($response->json() as $email) {
                        if ($email['primary']) {
                            $user->setEmail($email['email']);
                            break;
                        }
                    }                    
                } catch (Exception $e) {
                    $output->write('<error>failed</error>');
                }

                $output->writeln('');
            }

            $em->persist($user);
        }

        $em->flush();
    }
}