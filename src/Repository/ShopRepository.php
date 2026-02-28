<?php

namespace App\Repository;

use App\Entity\Shop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Shop>
 */
class ShopRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Shop::class);
    }

    public function findBySlug(string $slug): ?Shop
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    public function findDefault(): ?Shop
    {
        return $this->findOneBy([], ['id' => 'ASC']);
    }
}
