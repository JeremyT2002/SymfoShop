<?php

namespace App\Tests\Service\Cart;

use App\Entity\Product;
use App\Entity\ProductStatus;
use App\Entity\ProductVariant;
use App\Repository\ProductVariantRepository;
use App\Service\Cart\CartItem;
use App\Service\Cart\CartService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class CartServiceTest extends TestCase
{
    private CartService $cartService;
    private Session $session;
    private ProductVariantRepository $variantRepository;

    protected function setUp(): void
    {
        $this->session = new Session(new MockArraySessionStorage());
        $this->session->start();
        
        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getSession')->willReturn($this->session);
        
        $this->variantRepository = $this->createMock(ProductVariantRepository::class);
        $this->cartService = new CartService($requestStack, $this->variantRepository);
    }

    public function testAddItem(): void
    {
        $this->cartService->add(1, 2);

        $items = $this->cartService->getDetailedItems();
        $this->assertCount(0, $items); // No items because variant doesn't exist in mock

        $totals = $this->cartService->getTotals();
        $this->assertEquals(0, $totals['itemsCount']);
    }

    public function testAddItemWithExistingVariant(): void
    {
        $variant = $this->createMockVariant(1, 1000, 'EUR');
        $this->variantRepository->method('find')->willReturnMap([
            [1, $variant],
        ]);

        $this->cartService->add(1, 2);

        $items = $this->cartService->getDetailedItems();
        $this->assertCount(1, $items);
        $this->assertEquals(2, $items[0]['quantity']);
        $this->assertEquals(2000, $items[0]['itemTotal']);

        $totals = $this->cartService->getTotals();
        $this->assertEquals(1, $totals['itemsCount']);
        $this->assertEquals(2, $totals['totalQuantity']);
        $this->assertEquals(2000, $totals['subtotal']);
    }

    public function testAddItemIncrementsQuantity(): void
    {
        $variant = $this->createMockVariant(1, 1000, 'EUR');
        $this->variantRepository->method('find')->willReturnMap([
            [1, $variant],
        ]);

        $this->cartService->add(1, 2);
        $this->cartService->add(1, 3);

        $items = $this->cartService->getDetailedItems();
        $this->assertCount(1, $items);
        $this->assertEquals(5, $items[0]['quantity']);
        $this->assertEquals(5000, $items[0]['itemTotal']);

        $totals = $this->cartService->getTotals();
        $this->assertEquals(5, $totals['totalQuantity']);
        $this->assertEquals(5000, $totals['subtotal']);
    }

    public function testUpdateItem(): void
    {
        $variant = $this->createMockVariant(1, 1000, 'EUR');
        $this->variantRepository->method('find')->willReturnMap([
            [1, $variant],
        ]);

        $this->cartService->add(1, 2);
        $this->cartService->update(1, 5);

        $items = $this->cartService->getDetailedItems();
        $this->assertCount(1, $items);
        $this->assertEquals(5, $items[0]['quantity']);
        $this->assertEquals(5000, $items[0]['itemTotal']);
    }

    public function testUpdateNonExistentItemThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cart item not found');

        $this->cartService->update(999, 1);
    }

    public function testRemoveItem(): void
    {
        $variant = $this->createMockVariant(1, 1000, 'EUR');
        $this->variantRepository->method('find')->willReturnMap([
            [1, $variant],
        ]);

        $this->cartService->add(1, 2);
        $this->cartService->remove(1);

        $items = $this->cartService->getDetailedItems();
        $this->assertCount(0, $items);

        $totals = $this->cartService->getTotals();
        $this->assertEquals(0, $totals['itemsCount']);
    }

    public function testClearCart(): void
    {
        $variant = $this->createMockVariant(1, 1000, 'EUR');
        $this->variantRepository->method('find')->willReturnMap([
            [1, $variant],
        ]);

        $this->cartService->add(1, 2);
        $this->cartService->add(2, 3);

        $this->cartService->clear();

        $items = $this->cartService->getDetailedItems();
        $this->assertCount(0, $items);

        $totals = $this->cartService->getTotals();
        $this->assertEquals(0, $totals['itemsCount']);
    }

    public function testGetDetailedItemsWithMultipleVariants(): void
    {
        $variant1 = $this->createMockVariant(1, 1000, 'EUR');
        $variant2 = $this->createMockVariant(2, 2000, 'EUR');
        
        $this->variantRepository->method('find')->willReturnMap([
            [1, $variant1],
            [2, $variant2],
        ]);

        $this->cartService->add(1, 2);
        $this->cartService->add(2, 3);

        $items = $this->cartService->getDetailedItems();
        $this->assertCount(2, $items);

        $totals = $this->cartService->getTotals();
        $this->assertEquals(2, $totals['itemsCount']);
        $this->assertEquals(5, $totals['totalQuantity']);
        $this->assertEquals(8000, $totals['subtotal']); // (2 * 1000) + (3 * 2000)
    }

    public function testGetDetailedItemsRemovesInvalidVariants(): void
    {
        $variant = $this->createMockVariant(1, 1000, 'EUR');
        $this->variantRepository->method('find')->willReturnMap([
            [1, $variant],
            [2, null], // Invalid variant
        ]);

        $this->cartService->add(1, 2);
        $this->cartService->add(2, 3);

        $items = $this->cartService->getDetailedItems();
        $this->assertCount(1, $items);
        $this->assertEquals(1, $items[0]['variant']->getId());
    }

    public function testGetTotalsWithEmptyCart(): void
    {
        $totals = $this->cartService->getTotals();

        $this->assertEquals(0, $totals['itemsCount']);
        $this->assertEquals(0, $totals['totalQuantity']);
        $this->assertEquals(0, $totals['subtotal']);
        $this->assertEquals('EUR', $totals['currency']);
    }

    public function testCartItemValidation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CartItem(0, 1);
    }

    public function testCartItemNegativeQuantity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CartItem(1, -1);
    }

    public function testCartItemWithQuantity(): void
    {
        $item = new CartItem(1, 5);
        $newItem = $item->withQuantity(10);

        $this->assertEquals(1, $newItem->variantId);
        $this->assertEquals(10, $newItem->quantity);
        $this->assertEquals(5, $item->quantity); // Original unchanged
    }

    private function createMockVariant(int $id, int $priceAmount, string $currency): ProductVariant
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn(1);
        $product->method('getName')->willReturn('Test Product');
        $product->method('getSlug')->willReturn('test-product');
        $product->method('getStatus')->willReturn(ProductStatus::ACTIVE);
        $product->method('getMedia')->willReturn(new \Doctrine\Common\Collections\ArrayCollection());

        $variant = $this->createMock(ProductVariant::class);
        $variant->method('getId')->willReturn($id);
        $variant->method('getProduct')->willReturn($product);
        $variant->method('getPriceAmount')->willReturn($priceAmount);
        $variant->method('getCurrency')->willReturn($currency);
        $variant->method('getSku')->willReturn('SKU-' . $id);
        $variant->method('getAttributes')->willReturn([]);

        return $variant;
    }
}

