<?php

namespace App\CoreBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Yaml\Yaml;

use App\Model\User;

class DemoSetupCommand extends ContainerAwareCommand
{
    public function configure()
    {
        $this
            ->setName('stage1:demo:setup')
            ->setDescription('Setups the demo')
            ->setDefinition([
                new InputOption('reinstall', 'r', InputOption::VALUE_NONE)
            ]);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $config = Yaml::parse($this->getContainer()->getParameter('kernel.root_dir').'/config/demo.yml');

        $em = $this->getContainer()->get('doctrine')->getManager();

        $userRepo = $em->getRepository('Model:User');

        if (null === $user = $userRepo->findOneByUsername($config['username'])) {
            $user = new User();
            $user->setUsername($config['username']);

            $output->writeln('user <info>'.$config['username'].'</info> not found, creating one');
        }

        $user->setAccessToken($config['access_token']);

        $em->persist($user);

        $importer = $this->getContainer()->get('app_core.github.import');
        $importer->setUser($user);

        $projectRepo = $em->getRepository('Model:Project');
        $websocketChannels = [];

        foreach ($config['projects'] as $fullName) {
            $project = $projectRepo->findOneByGithubFullName($fullName);

            if (null !== $project && $input->getOption('reinstall')) {
                $em->remove($project);
                $em->flush();

                $project = null;
            }

            if (null !== $project) {
                $output->writeln('demo project <info>'.$fullName.'</info> already exists');

                if (!$project->getUsers()->contains($user)) {
                    $output->writeln('  linking demo user');
                    $project->addUser($user);
                }
            } else {
                $output->writeln('importing demo project <info>'.$fullName.'</info>');
                $project = $importer->import($fullName, function($step) use ($output) {
                    $output->writeln('  - '.$step['label'].' ('.$step['id'].')');
                });                
            }

            $em->persist($project);

            $websocketChannels[] = $project->getChannel();
        }

        $em->flush();

        $output->writeln('updating websocket routing infos');

        $redis = $this->getContainer()->get('app_core.redis');
        $redis->del('channel:routing:demo');

        array_unshift($websocketChannels, 'channel:routing:demo');
        
        call_user_func_array(array($redis, 'sadd'), $websocketChannels);
    }
}