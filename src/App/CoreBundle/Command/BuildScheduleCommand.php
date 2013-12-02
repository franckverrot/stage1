<?php

namespace App\CoreBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use App\CoreBundle\Entity\Build;
use App\CoreBundle\Message\MessageInterface;

use InvalidArgumentException;
use DateTime;

class BuildScheduleCommand extends ContainerAwareCommand
{
    public function configure()
    {
        $this
            ->setName('build:schedule')
            ->setDefinition([
                new InputArgument('project_id', InputArgument::REQUIRED, 'The project id'),
                new InputArgument('ref', InputArgument::REQUIRED, 'The ref'),
            ]);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $doctrine = $this->getContainer()->get('doctrine');

        $project = $doctrine
            ->getRepository('AppCoreBundle:Project')
            ->find($input->getArgument('project_id'));

        if (!$project) {
            $project = $doctrine
                ->getRepository('AppCoreBundle:Project')
                ->findOneBySlug($input->getArgument('project_id'));
        }

        if (!$project) {
            throw new InvalidArgumentException('project not found');
        }

        $build = new Build();
        $build->setProject($project);
        $build->setStatus(Build::STATUS_SCHEDULED);
        $build->setRef($input->getArgument('ref'));

        $em = $doctrine->getManager();
        $em->persist($build);
        $em->flush();

        $buildProducer = $this->getContainer()->get('old_sound_rabbit_mq.build_producer');
        $buildProducer->publish(json_encode(['build_id' => $build->getId()]));

        $websocketProducer = $this->getContainer()->get('old_sound_rabbit_mq.websocket_producer');
        $messageFactory = $this->getContainer()->get('app_core.message.factory');

        $websocketProducer->publish($messageFactory->createBuildScheduled($build));
    }
}