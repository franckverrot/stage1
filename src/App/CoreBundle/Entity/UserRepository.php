<?php

namespace App\CoreBundle\Entity;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NoResultException;

use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserRepository extends EntityRepository implements UserProviderInterface
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

    public function loadUserByUsername($username) {
        return $this->getEntityManager()
            ->createQuery('SELECT u FROM
                AppCoreBundle:User u
                WHERE u.username = :username
                OR u.email = :username')
            ->setParameters(array(
                'username' => $username
            ))
            ->getOneOrNullResult();
    }

    public function refreshUser(UserInterface $user) {
        return $this->loadUserByUsername($user->getUsername());
    }

    public function supportsClass($class) {
        return $class === 'App\CoreBundle\Entity\User';
    }
}