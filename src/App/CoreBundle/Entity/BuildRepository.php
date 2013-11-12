<?php

namespace App\CoreBundle\Entity;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\ResultSetMappingBuilder;

use Exception;

use Doctrine\ORM\NoResultException;

class BuildRepository extends EntityRepository
{
    public function countPendingBuildsByProject(Project $project)
    {
        $query = $this->createQueryBuilder('b')
           ->select('count(b.id)')
            ->where('b.project = ?1')
            ->andWhere('b.status IN (?2)')
            ->setParameters([
                1 => $project->getId(),
                2 => [Build::STATUS_BUILDING, Build::STATUS_SCHEDULED]
            ])
            ->getQuery();

        return (int) $query->getSingleScalarResult();
    }

    public function findLastByRefs(Project $project)
    {
        $query = 'SELECT b.* FROM (SELECT * FROM build WHERE build.project_id = ? ORDER BY created_at DESC) b GROUP BY b.ref';

        $rsm = new ResultSetMappingBuilder($this->getEntityManager());
        $rsm->addRootEntityFromClassMetadata('App\\CoreBundle\\Entity\\Build', 'b');
        $query = $this->getEntityManager()->createNativeQuery($query, $rsm);
        $query->setParameter(1, $project->getId());

        return $query->execute();
    }

    /**
     * @todo a "previous build" is actually any build with the same host and with a status of "running"
     *       or not: we might need to be able to find a previousBuild by multiple criterias. For example
     *       when we want to approximate the time a build will take based on previous builds, a previous
     *       build is actually the previous build "running" or "obsolete" with the same branch and project
     */
    public function findPreviousBuild(Build $build)
    {
        if ($build->isDemo()) {
            return $this->findPreviousDemoBuild($build);
        }

        $query = $this->createQueryBuilder('b')
            ->select()
            ->where('b.project = ?1')
            ->andWhere('b.ref = ?2')
            ->andWhere('b.status IN(?3)')
            ->setParameters([
                1 => $build->getProject()->getId(),
                2 => $build->getRef(),
                3 => [Build::STATUS_RUNNING, Build::STATUS_OBSOLETE],
            ])
            ->setMaxResults(1)
            ->orderBy('b.createdAt', 'DESC')
            ->getQuery();

        try {
            $query->getSingleResult();
        } catch (NoResultException $e) {
            return null;
        }
    }

    public function findPreviousDemoBuild(Build $build)
    {
        $query = $this->createQueryBuilder('b')
            ->select()
            ->where('b.host = ?1')
            ->andWhere('b.status IN(?2)')
            ->setParameters([
                1 => $build->getHost(),
                2 => [Build::STATUS_RUNNING, Build::STATUS_OBSOLETE]
            ])
            ->setMaxResults(1)
            ->orderBy('b.createdAt', 'DESC')
            ->getQuery();

        try {
            return $query->getSingleResult();
        } catch (NoResultException $e) {
            return null;
        }
    }
}