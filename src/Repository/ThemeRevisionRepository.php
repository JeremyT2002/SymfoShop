<?php

namespace App\Repository;

use App\Entity\Theme;
use App\Entity\ThemeRevision;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ThemeRevision>
 */
class ThemeRevisionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ThemeRevision::class);
    }

    /** @return ThemeRevision[] */
    public function findByTheme(Theme $theme, int $limit = 20): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.theme = :theme')
            ->setParameter('theme', $theme)
            ->orderBy('r.version', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByThemeAndVersion(Theme $theme, int $version): ?ThemeRevision
    {
        return $this->findOneBy(['theme' => $theme, 'version' => $version]);
    }
}
