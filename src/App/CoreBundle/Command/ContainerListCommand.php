<?php

namespace App\CoreBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Console\Helper\TableHelper;

class ContainerListCommand extends ContainerAwareCommand
{
    public function configure()
    {
        $this
            ->setName('stage1:container:list')
            ->setDescription('Lists running containers')
            ->setDefinition([
                new InputOption('project', 'p', InputOption::VALUE_REQUIRED, 'Filter by project'),
                new InputOption('username', 'u', InputOption::VALUE_REQUIRED, 'Filter by username'),
                new InputOption('build_status', 'b', InputOption::VALUE_REQUIRED, 'Filter by build status'),
                new InputOption('sort', null, InputOption::VALUE_REQUIRED, 'Sort results'),
            ]);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $docker = $this->getContainer()->get('app_core.docker');

        $containers = $docker->getContainerManager()->findAll();

        if (count($containers) === 0) {
            $output->writeln('<error>No containers found.<error>');
            return;
        }

        $em = $this->getContainer()->get('doctrine')->getManager();
        $rp = $em->getRepository('AppCoreBundle:Build');

        $headers = [
            'id' => 'Id',
            'image' => 'Image',
            'container' => 'Cont.',
            'project' => 'Project',
            'branch' => 'Branch',
            'user' => 'User',
            'build_status' => 'Build Status',
            'container_status' => 'Cont. Status',
            'ssh_port' => 'Ssh Port',
        ];

        if ((null !== $sort = $input->getOption('sort')) && !array_key_exists($sort, $headers)) {
            $output->writeln('<error>Cannot sort by field</error> <info>'.$sort.'<info> <error>.</error>');
            $output->writeln('Available field for sorting:');

            foreach (array_keys($headers) as $field) {
                $output->writeln('  - <info>'.$field.'</info>');
            }

            return;
        }

        $projectFilter = $input->getOption('project');
        $usernameFilter = $input->getOption('username');
        $buildStatusFilter = $input->getOption('build_status');

        $rows = [];

        foreach ($containers as $container) {

            list(,$projectId,,$buildId) = explode('/', $container->getImage()->getRepository());
            $data = $container->getData();
            $build = $rp->find($buildId);

            $projectName = $build->getProject()->getGithubFullName();

            if (null !== $projectFilter && false === strpos($projectName, $projectFilter)) {
                continue;
            }

            $username = $build->getProject()->getUsers()->first()->getUsername();

            if (null !== $usernameFilter && false === strpos($username, $usernameFilter)) {
                continue;
            }

            $buildStatus = $build->getStatusLabel();

            if (null !== $buildStatusFilter && false === strpos($buildStatus, $buildStatusFilter)) {
                continue;
            }

            $rows[] = [
                $build->getId(),
                $container->getImage()->getName(),
                substr($container->getId(), 0, 8),
                $projectName,
                $build->getBranch()->getName(),
                $username,
                $build->getStatusLabel(),
                $data['Status'],
                $data['Ports'][1]['PublicPort']
            ];
        }

        if (null !== $sort) {
            $sortIndex = array_search($sort, array_keys($headers));
            usort($rows, function($a, $b) use ($sortIndex) {
                if ($a[$sortIndex] === $b[$sortIndex]) {
                    return 0;
                }

                return $a[$sortIndex] > $b[$sortIndex];
            });
        }

        if (count($rows) === 0) {
            $output->writeln('<error>No containers found.<error>');
            return;
        }

        $table = $this->getHelperSet()->get('table');

        $table->setHeaders(array_values($headers));
        $table->setRows($rows);
        $table->render($output);

        $output->writeln('');
        $output->writeln('Found <info>'.count($rows).'</info> running containers');            
    }
}