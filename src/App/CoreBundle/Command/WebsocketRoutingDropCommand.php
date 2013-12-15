<?php

namespace App\CoreBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class WebsocketRoutingDropCommand extends ContainerAwareCommand
{
    public function configure()
    {
        $this
            ->setName('websocket:routing:drop')
            ->setDescription('Drops all websocket routing information')
            ->setDefinition([
                new InputOption('force', 'f', InputOption::VALUE_NONE, 'Actually drop stuff'),
            ]);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$input->getOption('force')) {
            $output->writeln('<error>Use the --force, Luke.</error>');
            return;
        }

        $redis = $this->getContainer()->get('app_core.redis');

        foreach ($redis->keys('channel:routing:*') as $key) {
            $output->writeln('removing routing key <info>'.$key.'</info>');
            $redis->del($key);
        }
    }
}