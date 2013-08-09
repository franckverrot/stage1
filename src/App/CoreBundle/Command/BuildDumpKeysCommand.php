<?php

namespace App\CoreBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

use InvalidArgumentException;

class BuildDumpKeysCommand extends ContainerAwareCommand
{
    public function configure()
    {
        $this
            ->setName('build:keys:dump')
            ->setDescription('Dumps keys to be used to a specific build')
            ->setDefinition([
                new InputArgument('build_id', InputArgument::REQUIRED, 'The build id'),
                new InputOption('file', 'f', InputOption::VALUE_REQUIRED, 'The file to dump to', null),
            ]);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $build = $this->getContainer()->get('doctrine')->getRepository('AppCoreBundle:Build')->find($input->getArgument('build_id'));

        if (!$build) {
            throw new InvalidArgumentException('Could not find build');
        }

        if (null === $file = $input->getOption('file')) {
            $file = tempnam(sys_get_temp_dir(), 'build-ssh-keys-');
        }

        file_put_contents($file, $build->getProject()->getPrivateKey());
        file_put_contents($file.'.pub', $build->getProject()->getPublicKey());

        $output->writeln($file);
    }
}