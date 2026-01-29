<?php

namespace App\Twig;

use App\Service\Cart\CartService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CartExtension extends AbstractExtension
{
    public function __construct(
        private readonly CartService $cartService
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('cart_totals', [$this, 'getCartTotals']),
            new TwigFunction('cart_items_count', [$this, 'getCartItemsCount']),
        ];
    }

    public function getCartTotals(): array
    {
        return $this->cartService->getTotals();
    }

    public function getCartItemsCount(): int
    {
        $totals = $this->cartService->getTotals();
        return $totals['totalQuantity'];
    }
}

