<?php

namespace App\CoreBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ContainerListCommand extends ContainerAwareCommand
{
    public function configure()
    {
        $this
            ->setName('stage1:container:list')
            ->setDescription('Lists running containers');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $docker = $this->getContainer()->get('app_core.docker');

        $em = $this->getContainer()->get('doctrine')->getManager();
        $rp = $em->getRepository('AppCoreBundle:Build');

        $table = $this->getHelperSet()->get('table');
        $table->setHeaders([
            'Id',
            'Image',
            'Cont.',
            'Project',
            'User',
            'Build Created At',
            'Cont. Created At',
            'Build Status',
            'Cont. Status',
            'Ssh Port',
        ]);

        foreach ($docker->getContainerManager()->findAll() as $container) {

            list(,$projectId,,$buildId) = explode('/', $container->getImage()->getRepository());
            $data = $container->getData();
            $build = $rp->find($buildId);

            $table->addRow([
                $build->getId(),
                $container->getImage()->getName(),
                substr($container->getId(), 0, 8),
                $build->getProject()->getGithubFullName(),
                $build->getProject()->getUsers()->first()->getUsername(),
                $build->getCreatedAt()->format('Y-m-d H:i:s'),
                date('Y-m-d H:i:s', $data['Created']),
                $build->getStatusLabel(),
                $data['Status'],
                $data['Ports'][1]['PublicPort']
            ]);
        }

        $table->render($output);
    }
}