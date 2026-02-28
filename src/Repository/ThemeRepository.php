<?php

namespace App\Repository;

use App\Entity\Shop;
use App\Entity\Theme;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Theme>
 */
class ThemeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Theme::class);
    }

    public function findByShopAndSlug(?Shop $shop, string $slug): ?Theme
    {
        return $this->findOneBy(['shop' => $shop, 'slug' => $slug]);
    }

    /** @return Theme[] */
    public function findByShop(?Shop $shop): array
    {
        return $this->findBy(['shop' => $shop], ['updatedAt' => 'DESC']);
    }

    public function findPublishedByShop(?Shop $shop): ?Theme
    {
        return $this->findOneBy(
            ['shop' => $shop, 'status' => Theme::STATUS_PUBLISHED],
            ['updatedAt' => 'DESC']
        );
    }
}
