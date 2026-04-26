<?php

namespace App\EventSubscriber;

use App\Entity\Order;
use App\Service\Admin\DashboardMetricsService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
class OrderDashboardCacheInvalidationSubscriber
{
    public function __construct(
        private readonly TagAwareCacheInterface $cache,
    ) {
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $this->invalidateIfOrder($args);
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->invalidateIfOrder($args);
    }

    public function postRemove(LifecycleEventArgs $args): void
    {
        $this->invalidateIfOrder($args);
    }

    private function invalidateIfOrder(LifecycleEventArgs $args): void
    {
        if (!$args->getObject() instanceof Order) {
            return;
        }

        $this->cache->invalidateTags([DashboardMetricsService::CACHE_TAG]);
    }
}

