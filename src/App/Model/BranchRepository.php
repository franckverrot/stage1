<?php

namespace App\Model;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NoResultException;

class BranchRepository extends EntityRepository
{
    /**
     * @todo make a real query
     */
    public function findOneByProjectAndName(Project $project, $ref)
    {
        $query = $this->createQueryBuilder('b')
            ->select()
            ->where('b.project = ?1')
            ->andWhere('b.name = ?2')
            ->setParameters([1 => $project->getId(), 2 => $ref])
            ->getQuery();

        try {
            return $query->getSingleResult();
        } catch (NoResultException $e) {
            return null;
        }
    }
}