<?php

namespace App\CoreBundle\EventListener;

use Symfony\Bridge\Doctrine\RegistryInterface;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\Common\EventSubscriber;

use App\Model\Build;
use App\Model\PullRequest;

use Psr\Log\LoggerInterface;

class BuildPullRequestRelationSubscriber implements EventSubscriber
{
    /**
     * @var Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var Symfony\Bridge\Doctrine\RegistryInterface
     */
    private $doctrine;

    /**
     * @param Psr\Log\LoggerInterface                   $logger
     * @param Symfony\Bridge\Doctrine\RegistryInterface $doctrine
     */
    public function __construct(LoggerInterface $logger, RegistryInterface $doctrine)
    {
        $this->logger = $logger;
        $this->doctrine = $doctrine;
    }

    /**
     * @return array
     */
    public function getSubscribedEvents()
    {
        return ['prePersist'];
    }

    /**
     * @param LifecycleEventArgs
     */
    public function prePersist(LifecycleEventArgs $args)
    {
        $build = $args->getEntity();

        if (!$build instanceof Build || !$build->isPullRequest()) {
            return;
        }

        $em = $this->doctrine->getManager();
        
        $pr = $this->doctrine
            ->getRepository('Model:PullRequest')
            ->findOneBy([
                'project' => $build->getProject()->getId(),
                'ref' => $build->getRef(),
            ]);

        if (!$pr) {
            $this->logger->info('creating non-existing pr', [
                'project' => $build->getProject()->getId(),
                'ref' => $build->getRef()
            ]);

            $pr = PullRequest::fromGithubPayload($build->getPayload());
            $pr->setProject($build->getProject());
            $em->persist($pr);
        }

        $build->setPullRequest($pr);
    }
}