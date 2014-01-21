<?php

namespace App\CoreBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BuildKillCommand extends ContainerAwareCommand
{
    public function configure()
    {
        $this
            ->setName('stage1:build:kill')
            ->setDescription('Sends a build kill order')
            ->setDefinition([
                new InputArgument('build_id', InputArgument::REQUIRED, 'The build id'),
            ]);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $producer = $this->getContainer()->get('old_sound_rabbit_mq.kill_producer');
        $producer->publish(json_encode(['build_id' => $input->getArgument('build_id')]));
    }
}