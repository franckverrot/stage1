<?php

namespace App\CoreBundle\Command;

use App\CoreBundle\Entity\Build;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Exception;

class BuildEnforceLimitsCommand extends ContainerAwareCommand
{
    public function configure()
    {
        $this
            ->setName('stage1:build:enforce-limits')
            ->setDescription('Enforces build limits per user')
            ->setDefinition([
                new InputOption('limit', 'l', InputOption::VALUE_REQUIRED, 'The running builds limit'),
                new InputOption('force', 'f', InputOption::VALUE_NONE, 'Use the force'),
            ]);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        if (null === $limit = $input->getOption('limit')) {
            $limit = $this->getContainer()->getParameter('stage1_running_containers_per_user');
        }

        $em = $this->getContainer()->get('doctrine')->getManager();

        $users = $em->getRepository('AppCoreBundle:User')->findAll();
        $rp = $em->getRepository('AppCoreBundle:Build');
        $docker = $this->getContainer()->get('app_core.docker');

        $totalBuildsCount = $excessBuildsCount = $stoppedBuildsCount = 0;

        foreach ($users as $user) {
            $runningBuilds = $rp->findRunningBuildsByUser($user);

            if (count($runningBuilds) === 0) {
                continue;
            }

            $totalBuildsCount += count($runningBuilds);

            $output->writeln(sprintf('found <info>%d</info> running build for user <info>%s</info>', count($runningBuilds), $user->getUsername()));

            if (count($runningBuilds) <= $limit) {
                continue;
            }

            $excessBuilds = array_slice($runningBuilds, $limit);

            $excessBuildsCount += count($excessBuilds);

            foreach ($excessBuilds as $build) {
                $container = $build->getContainer();
                $output->writeln(sprintf('stopping excess container <info>%s</info> (<info>%s</info>)', substr($container->getId(), 0, 8), $build->getProject()->getGithubFullName()));

                if (!$input->getOption('force')) {
                    continue;
                }

                $build->setStatus(Build::STATUS_STOPPED);
                $build->setMessage('Per-user running containers limit reached');

                try {
                    $docker->getContainerManager()
                        ->stop($container)
                        ->remove($container);

                    $stoppedBuildsCount++;
                } catch (Exception $e) {
                    $output->writeln('  <error>could not stop container</error>');
                    $output->writeln(sprintf('  [%s] %s', get_class($e), $e->getMessage()));
                }

                $em->persist($build);
                $em->flush();
            }
        }

        $output->writeln('');
        $output->writeln(sprintf('found <info>%d</info> running builds', $totalBuildsCount));
        $output->writeln(sprintf('found <info>%d</info> excess builds', $excessBuildsCount));
        $output->writeln(sprintf('stopped <info>%d</info> builds', $stoppedBuildsCount));

        if (!$input->getOption('force')) {
            $output->writeln('<error>Use the --force.</error>');
        }
    }
}