<?php

namespace App\CoreBundle\Command;

use App\CoreBundle\Entity\GithubPayload;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use InvalidArgumentException;

class BuildPayloadAttachCommand extends ContainerAwareCommand
{
    public function configure()
    {
        $this
            ->setName('stage1:build:payload:attach')
            ->setDescription('Attaches a payload to a build')
            ->setDefinition([
                new InputArgument('build_id', InputArgument::REQUIRED, 'The build id'),
                new InputArgument('payload', InputArgument::REQUIRED, 'The payload file'),
            ]);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $em = $this->getContainer()->get('doctrine')->getManager();

        $rp = $em->getRepository('AppCoreBundle:Build');
        $build = $rp->find($input->getArgument('build_id'));

        if (!$build) {
            throw new InvalidArgumentException('Build not found');
        }

        $payload = new GithubPayload();
        $payload->setPayload(file_get_contents($input->getArgument('payload')));

        $payload->setBuild($build);

        $em->persist($payload);
        $em->flush();
    }
}