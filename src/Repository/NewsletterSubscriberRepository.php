<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\NewsletterSubscriber;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NewsletterSubscriber>
 */
class NewsletterSubscriberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NewsletterSubscriber::class);
    }

    public function findOneByEmail(string $email): ?NewsletterSubscriber
    {
        return $this->findOneBy(['email' => mb_strtolower(trim($email))]);
    }

    public function findOneByToken(string $token): ?NewsletterSubscriber
    {
        return $token !== '' ? $this->findOneBy(['token' => $token]) : null;
    }

    /**
     * @return list<NewsletterSubscriber>
     */
    public function findAllNewestFirst(): array
    {
        return $this->findBy([], ['createdAt' => 'DESC']);
    }

    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.confirmedAt IS NOT NULL')
            ->andWhere('s.unsubscribedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
