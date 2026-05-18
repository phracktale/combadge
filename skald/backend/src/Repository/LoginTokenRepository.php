<?php

namespace App\Repository;

use App\Entity\LoginToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LoginToken>
 */
class LoginTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoginToken::class);
    }

    public function findOneByHash(string $tokenHash): ?LoginToken
    {
        return $this->findOneBy(['tokenHash' => $tokenHash]);
    }
}
