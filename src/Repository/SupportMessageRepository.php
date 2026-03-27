<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SupportConversation;
use App\Entity\SupportMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SupportMessage>
 */
class SupportMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SupportMessage::class);
    }

    /**
     * @return list<SupportMessage>
     */
    public function findForConversation(SupportConversation $conversation, ?int $afterId = null, int $limit = 200): array
    {
        $qb = $this->createQueryBuilder('m')
            ->addSelect('a')
            ->leftJoin('m.attachments', 'a')
            ->andWhere('m.conversation = :conversation')
            ->setParameter('conversation', $conversation)
            ->orderBy('m.id', 'ASC')
            ->setMaxResults($limit);

        if ($afterId !== null) {
            $qb->andWhere('m.id > :afterId')->setParameter('afterId', $afterId);
        }

        return $qb->getQuery()->getResult();
    }
}

