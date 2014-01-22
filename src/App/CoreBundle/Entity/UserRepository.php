<?php

namespace App\CoreBundle\Entity;

use Doctrine\ORM\EntityRepository;

use Doctrine\ORM\NoResultException;

class UserRepository extends EntityRepository
{
    public function findOneBySpec($spec)
    {
        if (is_numeric($spec)) {
            return $this->find((integer) $spec);
        }

        return $this->findOneByUsername($spec);
    }

    public function findOneByGithubUsername($username)
    {
        return $this->findOneByUsername($username);
    }
}