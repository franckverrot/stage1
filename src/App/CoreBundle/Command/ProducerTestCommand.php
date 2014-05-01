<?php

namespace App\CoreBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use InvalidArgumentException;

class ProducerTestCommand extends ContainerAwareCommand
{
    public function configure()
    {
        $this
            ->setName('stage1:producer:test')
            ->setDescription('Adds an user to a project')
            ->setDefinition([
                new InputArgument('producer', InputArgument::REQUIRED, 'The producer name'),
                new InputArgument('message', InputArgument::REQUIRED, 'The message'),
            ]);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $producer = $this->getContainer()->get(sprintf('old_sound_rabbit_mq.%s_producer', $input->getArgument('producer')));
        $producer->publish($input->getArgument('message'));
    }
}