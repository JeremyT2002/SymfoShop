<?php

namespace App\Repository;

use App\Entity\ApiKey;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ApiKey>
 */
class ApiKeyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiKey::class);
    }

    public function findByKeyHash(string $keyHash): ?ApiKey
    {
        return $this->createQueryBuilder('ak')
            ->where('ak.keyHash = :keyHash')
            ->andWhere('ak.isActive = true')
            ->setParameter('keyHash', $keyHash)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findActiveKeysForUser(int $userId): array
    {
        return $this->createQueryBuilder('ak')
            ->where('ak.user = :userId')
            ->andWhere('ak.isActive = true')
            ->andWhere('(ak.expiresAt IS NULL OR ak.expiresAt > :now)')
            ->setParameter('userId', $userId)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('ak.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

