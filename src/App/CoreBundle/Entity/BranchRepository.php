<?php

namespace App\CoreBundle\Entity;

use Doctrine\ORM\EntityRepository;

class BranchRepository extends EntityRepository
{
    /**
     * @todo make a real query
     */
    public function findOneByProjectAndName(Project $project, $ref)
    {
        foreach ($project->getBranches() as $branch) {
            if ($branch->getName() === $ref) {
                return $branch;
            }
        }

        return null;
    }
}