<?php

namespace App\Tests\Service\Review;

use App\Entity\Product;
use App\Entity\User;
use App\Repository\OrderItemRepository;
use App\Service\Review\VerifiedPurchaseChecker;
use PHPUnit\Framework\TestCase;

class VerifiedPurchaseCheckerTest extends TestCase
{
    public function testHasVerifiedPurchaseReturnsTrueWhenRepositoryFindsPurchase(): void
    {
        $repository = $this->createMock(OrderItemRepository::class);
        $checker = new VerifiedPurchaseChecker($repository);
        $user = new User();
        $product = new Product();

        $repository->expects($this->once())
            ->method('existsVerifiedPurchase')
            ->with($user, $product)
            ->willReturn(true);

        $this->assertTrue($checker->hasVerifiedPurchase($user, $product));
    }

    public function testHasVerifiedPurchaseReturnsFalseWhenRepositoryFindsNoPurchase(): void
    {
        $repository = $this->createMock(OrderItemRepository::class);
        $checker = new VerifiedPurchaseChecker($repository);
        $user = new User();
        $product = new Product();

        $repository->expects($this->once())
            ->method('existsVerifiedPurchase')
            ->with($user, $product)
            ->willReturn(false);

        $this->assertFalse($checker->hasVerifiedPurchase($user, $product));
    }
}

