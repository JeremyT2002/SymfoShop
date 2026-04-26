<?php

namespace App\Service\Review;

use App\Entity\Product;
use App\Entity\User;
use App\Repository\OrderItemRepository;

class VerifiedPurchaseChecker
{
    public function __construct(
        private readonly OrderItemRepository $orderItemRepository
    ) {
    }

    public function hasVerifiedPurchase(User $user, Product $product): bool
    {
        return $this->orderItemRepository->existsVerifiedPurchase($user, $product);
    }
}

