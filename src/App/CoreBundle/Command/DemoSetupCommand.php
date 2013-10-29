<?php

namespace App\CoreBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Yaml\Yaml;

use App\CoreBundle\Entity\User;

use Closure;

class DemoSetupCommand extends ContainerAwareCommand
{
    public function configure()
    {
        $this
            ->setName('demo:setup');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $config = Yaml::parse($this->getContainer()->getParameter('kernel.root_dir').'/config/demo.yml');

        $em = $this->getContainer()->get('doctrine')->getManager();

        $userRepo = $em->getRepository('AppCoreBundle:User');

        if (null === $user = $userRepo->findOneByUsername('Demo')) {
            $user = new User();
            $user->setUsername('Demo');

            $em->persist($user);

            $output->writeln('user <info>demo</info> not found, creating one');
        }

        $user->setAccessToken($config['access_token']);

        $importer = $this->getContainer()->get('app_core.github.import');
        $importer->setUser($user);

        $projectRepo = $em->getRepository('AppCoreBundle:Project');

        foreach ($config['projects'] as $fullName) {

            if (null !== $project = $projectRepo->findOneByGithubFullName($fullName)) {
                $output->writeln('demo project <info>'.$fullName.'</info> already exists');

                if (!$project->getUsers()->contains($user)) {
                    $project->addUser($user);
                }
            } else {
                $output->writeln('importing demo project <info>'.$fullName.'</info>');
                $importer->import($fullName, function($step) use ($output) {
                    $output->writeln('  - '.$step['label'].' ('.$step['id'].')');
                });                
            }
        }
    }
}