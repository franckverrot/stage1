<?php

namespace App\CoreBundle\EventListener;

use Symfony\Bridge\Doctrine\RegistryInterface;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\Common\EventSubscriber;

use App\CoreBundle\Entity\Build;
use App\CoreBundle\Entity\Branch;

use DateTime;

/**
 * Whenever a build is created, checks if there exists a corresponding
 * branch record. If not create it. in any case, create a relation between
 * the build and the branch records.
 */
class BuildBranchRelationSubscriber implements EventSubscriber
{
    private $doctrine;

    public function __construct(RegistryInterface $doctrine)
    {
        $this->doctrine = $doctrine;
    }

    public function getDoctrine()
    {
        return $this->doctrine;
    }

    public function getSubscribedEvents()
    {
        return ['prePersist'];
    }

    public function prePersist(LifecycleEventArgs $args)
    {
        $build = $args->getEntity();

        if (!$build instanceof Build) {
            return;
        }

        $em = $this->getDoctrine()->getManager();
        
        $branch = $this
            ->getDoctrine()
            ->getRepository('AppCoreBundle:Branch')
            ->findOneByName($build->getRef());

        if (!$branch) {
            $branch = new Branch();
            $branch->setName($build->getRef());
            $branch->setProject($build->getProject());

            $em->persist($branch);

            $build->getProject()->addBranch($branch);
        }

        $build->setBranch($branch);
        $branch->addBuild($build);
    }
}