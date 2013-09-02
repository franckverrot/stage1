<?php

namespace App\CoreBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use App\CoreBundle\Entity\Build;

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

    protected function publishWebsocket($event, $channel, $data)
    {
        $this->getContainer()->get('old_sound_rabbit_mq.websocket_producer')->publish(json_encode([
            'event' => $event,
            'channel' => $channel,
            'timestamp' => microtime(true),
            'data' => $data,
        ]));
    }

    public function generateUrl($route, $parameters = array(), $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH)
    {
        return $this->getContainer()->get('router')->generate($route, $parameters, $referenceType);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $doctrine = $this->getContainer()->get('doctrine');

        $project = $doctrine
            ->getRepository('AppCoreBundle:Project')
            ->find($input->getArgument('project_id'));

        if (!$project) {
            $project = $doctrine->getRepository('AppCoreBundle:Project')->findOneBySlug($input->getArgument('project_id'));
        }

        if (!$project) {
            throw new InvalidArgumentException('project not found');
        }

        $build = new Build();
        $build->setProject($project);
        $build->setStatus(Build::STATUS_SCHEDULED);
        $build->setRef($input->getArgument('ref'));

        $now = new DateTime();
        $build->setCreatedAt($now);
        $build->setUpdatedAt($now);

        $em = $doctrine->getManager();
        $em->persist($build);
        $em->flush();

        $producer = $this->getContainer()->get('old_sound_rabbit_mq.build_producer');
        $producer->publish(json_encode(['build_id' => $build->getId()]));

        $this->publishWebsocket('build.scheduled', $project->getChannel(), [
            'build' => array_replace([
                'show_url' => $this->generateUrl('app_core_build_show', ['id' => $build->getId()]),
                'cancel_url' => $this->generateUrl('app_core_build_cancel', ['id' => $build->getId()]),
            ], $build->asWebsocketMessage()),
            'project' => $project->asWebsocketMessage(),
        ]);
    }
}