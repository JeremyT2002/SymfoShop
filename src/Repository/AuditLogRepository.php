<?php

namespace App\Repository;

use App\Entity\AuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuditLog>
 */
class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    /**
     * Find audit logs for a specific entity
     *
     * @return AuditLog[]
     */
    public function findByEntity(string $entityType, ?int $entityId = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->where('a.entityType = :entityType')
            ->setParameter('entityType', $entityType)
            ->orderBy('a.createdAt', 'DESC');

        if ($entityId !== null) {
            $qb->andWhere('a.entityId = :entityId')
                ->setParameter('entityId', $entityId);
        }

        return $qb->getQuery()->getResult();
    }
}

