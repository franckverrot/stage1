<?php

namespace App\CoreBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

use InvalidArgumentException;

class BuildInfosCommand extends ContainerAwareCommand
{
    public function configure()
    {
        $this
            ->setName('build:infos')
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

        $infos = vsprintf('declare BUILD_ID=%s REF=%s HASH=%s SSH_URL=%s ACCESS_TOKEN=%s COMMIT_NAME=%s COMMIT_TAG=%s BUILD_DOMAIN=%s', [
            $build->getId(),
            $build->getRef(),
            $build->getHash(),
            $build->getProject()->getSshUrl(),
            $build->getProject()->getOwner()->getAccessToken(),
            $build->getImageName(),
            $build->getImageTag(),
            sprintf($this->getContainer()->getParameter('build_url_mask'), $build->getBranchDomain()),
        ]);

        $output->writeln($infos);
    }
}