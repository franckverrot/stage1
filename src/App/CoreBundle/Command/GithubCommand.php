<?php

namespace App\CoreBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GithubCommand extends ContainerAwareCommand
{
    public function configure()
    {
        $this
            ->setName('github')
            ->setDescription('Runs requests against the Github API')
            ->setDefinition([
                new InputOption('user', 'u', InputOption::VALUE_REQUIRED, 'User to get an access token from'),
                new InputArgument('path', InputArgument::REQUIRED, 'Path to query')
            ]);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $client = $this->getContainer()->get('app_core.client.github');
        $client->setDefaultOption('headers/Accept', 'application/vnd.github.v3');

        if ($input->getOption('user')) {
            $repo = $this->getContainer()->get('doctrine')->getRepository('AppCoreBundle:User');
            $user = $repo->findOneBySpec($input->getOption('user'));

            if (!$user) {
                throw new RuntimeException(sprintf('Could not impersonate user "%s"', $input->getOption('user')));
            }

            $output->writeln('impersonating <info>'.$user->getUsername().'</info> with token <info>'.$user->getAccessToken().'</info>');

            $client->setDefaultOption('headers/Authorization', 'token '.$user->getAccessToken());
        }

        $request = $client->get($input->getArgument('path'));
        $response = $request->send();

        $output->writeln(json_encode($response->json(), JSON_PRETTY_PRINT));    
    }
}