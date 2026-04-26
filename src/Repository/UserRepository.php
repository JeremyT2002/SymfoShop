<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface as SecurityUserInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Find user by email for authentication (only active users)
     */
    public function findOneByEmailForAuth(string $email): ?User
    {
        return $this->createQueryBuilder('u')
            ->where('u.email = :email')
            ->andWhere('u.isActive = :active')
            ->setParameter('email', $email)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countCreatedBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.createdAt >= :from')
            ->andWhere('u.createdAt < :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<User>
     */
    public function findForAdminList(
        ?string $search,
        ?string $role,
        int $limit,
        int $offset,
        string $sortBy = 'createdAt',
        string $sortDir = 'DESC'
    ): array {
        $sortBy = $this->resolveAdminSortField($sortBy);
        $sortDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        $qb = $this->createQueryBuilder('u')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->orderBy('u.' . $sortBy, $sortDir);

        if ($search !== null && trim($search) !== '') {
            $term = '%' . mb_strtolower(trim($search)) . '%';
            $qb->andWhere('LOWER(u.email) LIKE :term OR LOWER(u.firstName) LIKE :term OR LOWER(u.lastName) LIKE :term')
                ->setParameter('term', $term);
        }

        if ($role !== null && $role !== '') {
            $qb->andWhere('u.roles LIKE :roleLike')
                ->setParameter('roleLike', '%"' . $role . '"%');
        }

        return $qb->getQuery()->getResult();
    }

    public function countForAdminList(?string $search, ?string $role): int
    {
        $qb = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)');

        if ($search !== null && trim($search) !== '') {
            $term = '%' . mb_strtolower(trim($search)) . '%';
            $qb->andWhere('LOWER(u.email) LIKE :term OR LOWER(u.firstName) LIKE :term OR LOWER(u.lastName) LIKE :term')
                ->setParameter('term', $term);
        }

        if ($role !== null && $role !== '') {
            $qb->andWhere('u.roles LIKE :roleLike')
                ->setParameter('roleLike', '%"' . $role . '"%');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function resolveAdminSortField(string $requested): string
    {
        $allowed = ['id', 'email', 'isActive', 'createdAt', 'lastLoginAt'];

        return in_array($requested, $allowed, true) ? $requested : 'createdAt';
    }
}

