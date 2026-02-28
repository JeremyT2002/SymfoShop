<?php

namespace App\Dashboard;

use App\Entity\AdminDashboardConfig;
use App\Entity\User;
use App\Repository\AdminDashboardConfigRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Resolves and persists dashboard config (global + per-user merge).
 */
class AdminDashboardConfigService
{
    private const DEFAULT_WIDGETS = [
        ['id' => 'w1', 'type' => 'kpi_products', 'x' => 0, 'y' => 0, 'w' => 2, 'h' => 1, 'settings' => []],
        ['id' => 'w2', 'type' => 'kpi_orders', 'x' => 2, 'y' => 0, 'w' => 2, 'h' => 1, 'settings' => []],
        ['id' => 'w3', 'type' => 'kpi_users', 'x' => 4, 'y' => 0, 'w' => 2, 'h' => 1, 'settings' => []],
        ['id' => 'w4', 'type' => 'chart_sales', 'x' => 0, 'y' => 1, 'w' => 4, 'h' => 2, 'settings' => ['days' => 30]],
        ['id' => 'w5', 'type' => 'chart_orders_by_status', 'x' => 4, 'y' => 1, 'w' => 2, 'h' => 2, 'settings' => []],
        ['id' => 'w6', 'type' => 'recent_orders', 'x' => 0, 'y' => 3, 'w' => 6, 'h' => 2, 'settings' => ['limit' => 5]],
    ];

    public function __construct(
        private readonly AdminDashboardConfigRepository $repository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Get effective config for user: user override if present, else global, else default.
     * @return array{widgets: array<int, array>, nav?: array<int, array>}
     */
    public function getEffectiveConfig(?User $user): array
    {
        $userConfig = $user !== null ? $this->repository->findByOwner($user) : null;
        if ($userConfig !== null && $userConfig->getConfigJson() !== [] && $this->hasWidgets($userConfig->getConfigJson())) {
            return $this->normalizeConfig($userConfig->getConfigJson());
        }
        $global = $this->repository->findGlobal();
        if ($global !== null && $this->hasWidgets($global->getConfigJson())) {
            return $this->normalizeConfig($global->getConfigJson());
        }
        return $this->getDefaultConfig();
    }

    public function getDefaultConfig(): array
    {
        return ['widgets' => self::DEFAULT_WIDGETS, 'nav' => []];
    }

    public function getConfigForUser(?User $user): ?AdminDashboardConfig
    {
        if ($user === null) {
            return $this->repository->findGlobal();
        }
        return $this->repository->findByOwner($user);
    }

    public function saveConfig(?User $owner, array $config): AdminDashboardConfig
    {
        $config = $this->normalizeConfig($config);
        if ($owner === null) {
            $entity = $this->repository->findGlobalOrCreate();
        } else {
            $entity = $this->repository->findForUserOrCreate($owner);
        }
        $entity->setConfigJson($config);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
        return $entity;
    }

    /** @param array{widgets?: array, nav?: array} $config */
    private function hasWidgets(array $config): bool
    {
        $widgets = $config['widgets'] ?? [];
        return is_array($widgets) && count($widgets) > 0;
    }

    /** @param array{widgets?: array, nav?: array} $config */
    private function normalizeConfig(array $config): array
    {
        $widgets = $config['widgets'] ?? [];
        $nav = $config['nav'] ?? [];
        return [
            'widgets' => is_array($widgets) ? $widgets : [],
            'nav' => is_array($nav) ? $nav : [],
        ];
    }
}
