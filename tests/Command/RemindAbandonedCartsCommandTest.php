<?php

namespace App\Tests\Command;

use App\Command\RemindAbandonedCartsCommand;
use App\Entity\Cart;
use App\Message\SendAbandonedCartReminderMessage;
use App\Repository\CartRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class RemindAbandonedCartsCommandTest extends TestCase
{
    public function testDispatchesMessagesForEligibleCarts(): void
    {
        $cart = (new Cart())->setUpdatedAt(new \DateTimeImmutable('-2 days'));
        $ref = new \ReflectionProperty($cart, 'id');
        $ref->setAccessible(true);
        $ref->setValue($cart, 123);

        $repo = $this->createMock(CartRepository::class);
        $repo->method('findAbandonedCarts')->willReturn([$cart]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(fn ($m) => $m instanceof SendAbandonedCartReminderMessage && $m->getCartId() === 123))
            ->willReturn(new Envelope(new \stdClass()));

        $command = new RemindAbandonedCartsCommand($repo, $bus);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
    }
}

