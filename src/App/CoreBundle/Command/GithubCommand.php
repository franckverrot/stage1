<?php

namespace App\CoreBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Guzzle\Http\Exception\ClientErrorResponseException;

class GithubCommand extends ContainerAwareCommand
{
    public function configure()
    {
        $this
            ->setName('stage1:github')
            ->setDescription('Runs requests against the Github API')
            ->setDefinition([
                new InputOption('user', 'u', InputOption::VALUE_REQUIRED, 'User to get an access token from'),
                new InputOption('method', 'm', InputOption::VALUE_REQUIRED, 'The HTTP method', 'GET'),
                new InputArgument('path', InputArgument::REQUIRED, 'Path to query'),
                new InputArgument('body', InputArgument::OPTIONAL, 'The request body', null)
            ]);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $client = $this->getContainer()->get('app_core.client.github');
        $client->setDefaultOption('headers/Accept', 'application/vnd.github.v3');

        if ($input->getOption('user')) {
            $repo = $this->getContainer()->get('doctrine')->getRepository('Model:User');
            $user = $repo->findOneBySpec($input->getOption('user'));

            if (!$user) {
                throw new RuntimeException(sprintf('Could not impersonate user "%s"', $input->getOption('user')));
            }

            $output->writeln('impersonating <info>'.$user->getUsername().'</info> with token <info>'.$user->getAccessToken().'</info>');

            $client->setDefaultOption('headers/Authorization', 'token '.$user->getAccessToken());
        }

        $method = strtolower($input->getOption('method'));

        $request = $client->$method($input->getArgument('path'));

        if (null !== $input->getArgument('body')) {
            $request->setBody($input->getArgument('body'));
        }

        try {
            $response = $request->send();
        } catch (ClientErrorResponseException $e) {
            $output->writeln('<error>Error sending request</error>');

            $output->writeln('');
            $output->writeln('<info>Request</info>');
            $output->writeln(sprintf('<comment>Request URL</comment>  => %s', $request->getUrl()));
            $output->writeln(sprintf('<comment>Request Body</comment> => %s', (string) $request->getBody()));

            $output->writeln('');
            $output->writeln('<info>Response</info>');
            $output->writeln(sprintf('<comment>Status Code</comment>   => %s', $e->getResponse()->getStatusCode()));
            $output->writeln(sprintf('<comment>Reason Phrase</comment> => %s', $e->getResponse()->getReasonPhrase()));

            $output->writeln('');
            $output->writeln('<info>Response Body</info>');
            $output->writeln('');
            $output->writeln((string) $e->getResponse()->getBody());
        }

        if (isset($response)) {
            $output->writeln(json_encode($response->json(), JSON_PRETTY_PRINT));    
        }
    }
}