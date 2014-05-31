<?php

namespace App\CoreBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

use InvalidArgumentException;

class BranchSetDomainCommand extends ContainerAwareCommand
{
    public function configure()
    {
        $this
            ->setName('stage1:branch:set-domain')
            ->setDescription('Sets the static domain of a branch')
            ->setDefinition([
                new InputArgument('project_spec', InputArgument::REQUIRED, 'The project spec'),
                new InputArgument('branch_spec', InputArgument::REQUIRED, 'The branch spec'),
                new InputArgument('domain', InputArgument::OPTIONAL, 'The static domain'),
                new InputOption('unset', null, InputOption::VALUE_NONE, 'Unset demo status'),
            ]);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        if (null === $input->getArgument('domain') && !$input->getOption('unset')) {
            throw new InvalidArgumentException('one of domain or --unset is required');
        }

        $project = $this
            ->getContainer()
            ->get('doctrine')
            ->getRepository('Model:Project')
            ->findOneBySpec($input->getArgument('project_spec'));

        $branch = $this
            ->getContainer()
            ->get('doctrine')
            ->getRepository('Model:Branch')
            ->findOneByProjectAndName($project, $input->getArgument('branch_spec'));

        if ($input->getOption('unset')) {
            $branch->setDomain(null);
        } else {
            $branch->setDomain($input->getArgument('domain'));
        }

        $em = $this
            ->getContainer()
            ->get('doctrine')
            ->getManager();

        $em->persist($branch);
        $em->flush();

        if ($input->getOption('unset')) {
            $output->writeln('<info>unset</info> demo status for branch <info>'.$project->getGithubFullName().':'.$branch->getName().'</info>');
        } else {
            $output->writeln('<info>set</info> demo status for branch <info>'.$project->getGithubFullName().':'.$branch->getName().'</info>');
        }
    }
}