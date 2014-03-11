<?php

namespace App\Model;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\ResultSetMappingBuilder;

use Doctrine\ORM\NoResultException;

class BuildRepository extends EntityRepository
{
    public function findNewerScheduledBuilds(Build $build)
    {
        $query = $this->createQueryBuilder('b')
            ->select()
            ->leftJoin('b.project', 'p')
            ->where('p.id = ?1')
            ->andWhere('b.ref = ?2')
            ->andWhere('b.createdAt > ?3')
            ->setParameters([
                1 => $build->getProject()->getId(),
                2 => $build->getRef(),
                3 => $build->getCreatedAt()->format('Y-m-d H:i:s'),
            ])
            ->getQuery();

        return $query->execute();
    }

    /**
     * @param App\Model\Project $project
     * @param string                        $ref
     * 
     * @return Doctrine\Common\Collections\Collection
     */
    public function findPendingByRef(Project $project, $ref)
    {
        $query = $this->createQueryBuilder('b')
            ->select()
            ->leftJoin('b.project', 'p')
            ->where('p.id = ?1')
            ->andWhere('b.ref = ?2')
            ->andWhere('b.status IN (?3)')
            ->setParameters([
                1 => $project->getId(),
                2 => $ref,
                3 => [Build::STATUS_SCHEDULED, Build::STATUS_BUILDING]
            ])
            ->getQuery();

        return $query->execute();

    }

    /**
     * @param integer $ttl
     * 
     * @return Doctrine\Common\Collections\Collection
     */
    public function findTimeouted($ttl)
    {
        $query = 'SELECT b.* FROM build AS b WHERE TIME_TO_SEC(TIMEDIFF(NOW(), b.created_at)) >= ? AND b.status = ?';

        $rsm = new ResultSetMappingBuilder($this->getEntityManager());
        $rsm->addRootEntityFromClassMetadata('App\\Model\\Build', 'b');
        $query = $this->getEntityManager()->createNativeQuery($query, $rsm);

        $query->setParameter(1, $ttl);
        $query->setParameter(2, Build::STATUS_BUILDING);

        return $query->execute();
    }

    /**
     * @param App\Model\User $user
     * 
     * @return Doctrine\Common\Collections\Collection
     */
    public function findRunningBuildsByUser(User $user)
    {
        $query = $this->createQueryBuilder('b')
            ->select()
            ->leftJoin('b.project', 'p')
            ->leftJoin('p.users', 'u')
            ->where('u.id = ?1')
            ->andWhere('b.status = ?2')
            ->orderBy('b.createdAt', 'DESC')
            ->setParameters([
                1 => $user->getId(),
                2 => Build::STATUS_RUNNING,
            ])
            ->getQuery();

        return $query->execute();

    }

    /**
     * @param App\Model\Project $project
     * 
     * @return integer
     */
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

    /**
     * @param App\Model\Project $project
     * 
     * @return Doctrine\Common\Collections\Collection
     */
    public function findLastByRefs(Project $project)
    {
        $query = 'SELECT b.* FROM (SELECT * FROM build WHERE build.project_id = ? ORDER BY created_at DESC) b GROUP BY b.ref';

        $rsm = new ResultSetMappingBuilder($this->getEntityManager());
        $rsm->addRootEntityFromClassMetadata('App\\Model\\Build', 'b');
        $query = $this->getEntityManager()->createNativeQuery($query, $rsm);
        $query->setParameter(1, $project->getId());

        return $query->execute();
    }

    /**
     * @todo a "previous build" is actually any build with the same host and with a status of "running"
     *       or not: we might need to be able to find a previousBuild by multiple criterias. For example
     *       when we want to approximate the time a build will take based on previous builds, a previous
     *       build is actually the previous build "running" or "obsolete" with the same branch and project
     * 
     * @param App\Model\Build $build
     * @param boolean $demo
     * 
     * @return null|App\Model\Build
     */
    public function findPreviousBuild(Build $build, $demo = false)
    {
        if ($build->isDemo() && $demo) {
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
            return $query->getSingleResult();
        } catch (NoResultException $e) {
            return null;
        }
    }

    /**
     * @param App\Model\Build $build
     * 
     * @return null|App\Model\Build
     */
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