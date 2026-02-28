<?php

namespace App\Repository;

use App\Entity\AdminDashboardConfig;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AdminDashboardConfig>
 */
class AdminDashboardConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdminDashboardConfig::class);
    }

    public function findGlobal(): ?AdminDashboardConfig
    {
        return $this->findOneBy(['owner' => null], ['id' => 'ASC']);
    }

    public function findByOwner(User $owner): ?AdminDashboardConfig
    {
        return $this->findOneBy(['owner' => $owner], ['id' => 'ASC']);
    }

    public function findGlobalOrCreate(): AdminDashboardConfig
    {
        $config = $this->findGlobal();
        if ($config !== null) {
            return $config;
        }
        $config = new AdminDashboardConfig();
        $config->setOwner(null);
        $config->setConfigJson(['widgets' => [], 'nav' => []]);
        return $config;
    }

    public function findForUserOrCreate(User $user): AdminDashboardConfig
    {
        $config = $this->findByOwner($user);
        if ($config !== null) {
            return $config;
        }
        $config = new AdminDashboardConfig();
        $config->setOwner($user);
        $config->setConfigJson(['widgets' => [], 'nav' => []]);
        return $config;
    }
}
