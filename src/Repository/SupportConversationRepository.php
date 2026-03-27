<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SupportConversation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SupportConversation>
 */
class SupportConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SupportConversation::class);
    }

    /**
     * @return list<SupportConversation>
     */
    public function findForCustomer(User $user, int $limit = 100): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.customer = :customer')
            ->setParameter('customer', $user)
            ->orderBy('c.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<SupportConversation>
     */
    public function findForAdminInbox(int $limit = 200): array
    {
        return $this->createQueryBuilder('c')
            ->addSelect('customer')
            ->leftJoin('c.customer', 'customer')
            ->orderBy('c.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}

