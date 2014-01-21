<?php

namespace App\CoreBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

use InvalidArgumentException;

class BuildInfosCommand extends ContainerAwareCommand
{
    public function configure()
    {
        $this
            ->setName('stage1:build:infos')
            ->setDescription('Dumps build infos to be used by the build wrapper')
            ->setDefinition([
                new InputArgument('build_id', InputArgument::REQUIRED, 'The build id'),
            ]);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $build =  $this->getContainer()->get('doctrine')->getRepository('AppCoreBundle:Build')->find($input->getArgument('build_id'));

        if (!$build) {
            throw new InvalidArgumentException(sprintf('Could not find build #%d', $input->getArgument('build_id')));
        }

        $infos = vsprintf('declare BUILD_ID=%s REF=%s HASH=%s SSH_URL=%s ACCESS_TOKEN=%s COMMIT_NAME=%s COMMIT_TAG=%s BUILD_HOST=%s', [
            $build->getId(),
            $build->getRef(),
            $build->getHash(),
            $build->getProject()->getSshUrl(),
            # @todo there must be a way to avoid requiring a valid access token
            #       I think the token is only used to avoid hitting github's
            #       API limit through composer, so maybe there's a way to use a
            #       stage1 specific token instead
            $build->getProject()->getUsers()->first()->getAccessToken(),
            $build->getImageName(),
            $build->getImageTag(),
            $build->getHost(),
        ]);

        $output->writeln($infos);
    }
}