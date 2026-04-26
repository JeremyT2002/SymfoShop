<?php

namespace App\Tests\Service\Inventory;

use App\Message\ProcessStockNotificationsMessage;
use App\Service\Inventory\StockRestockNotifier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class StockRestockNotifierTest extends TestCase
{
    public function testDispatchesWhenStockTransitionsFromZeroToPositive(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(fn ($message) => $message instanceof ProcessStockNotificationsMessage && $message->getVariantId() === 42))
            ->willReturn(new Envelope(new \stdClass()));

        $notifier = new StockRestockNotifier($bus);
        $notifier->dispatchIfRestocked(0, 3, 42);
    }

    public function testDoesNotDispatchWhenStockWasAlreadyPositive(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $notifier = new StockRestockNotifier($bus);
        $notifier->dispatchIfRestocked(2, 5, 42);
    }
}

