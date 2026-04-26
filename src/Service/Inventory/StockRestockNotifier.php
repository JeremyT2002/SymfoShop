<?php

namespace App\Service\Inventory;

use App\Message\ProcessStockNotificationsMessage;
use Symfony\Component\Messenger\MessageBusInterface;

class StockRestockNotifier
{
    public function __construct(
        private readonly MessageBusInterface $messageBus
    ) {
    }

    public function dispatchIfRestocked(int $beforeAvailable, int $afterAvailable, int $variantId): void
    {
        if ($beforeAvailable <= 0 && $afterAvailable > 0) {
            $this->messageBus->dispatch(new ProcessStockNotificationsMessage($variantId));
        }
    }
}

