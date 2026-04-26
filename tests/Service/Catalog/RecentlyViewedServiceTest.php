<?php

namespace App\Tests\Service\Catalog;

use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Service\Catalog\RecentlyViewedService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class RecentlyViewedServiceTest extends TestCase
{
    private Session $session;
    private RequestStack $requestStack;
    private ProductRepository $productRepository;

    protected function setUp(): void
    {
        $this->session = new Session(new MockArraySessionStorage());
        $this->session->start();
        $request = Request::create('/');
        $request->cookies->set('symfoshop_cookie_consent', 'all');
        $request->setSession($this->session);
        $this->requestStack = new RequestStack();
        $this->requestStack->push($request);
        $this->productRepository = $this->createMock(ProductRepository::class);
    }

    public function testAddDeduplicatesAndKeepsNewestFirst(): void
    {
        $service = new RecentlyViewedService($this->requestStack, $this->productRepository);
        $service->addProduct($this->makeProduct(1));
        $service->addProduct($this->makeProduct(2));
        $service->addProduct($this->makeProduct(1));

        $this->assertSame([1, 2], $service->getProductIds());
    }

    public function testAddKeepsMaximumTenItems(): void
    {
        $service = new RecentlyViewedService($this->requestStack, $this->productRepository);
        for ($i = 1; $i <= 12; $i++) {
            $service->addProduct($this->makeProduct($i));
        }

        $this->assertCount(10, $service->getProductIds());
        $this->assertSame([12, 11, 10, 9, 8, 7, 6, 5, 4, 3], $service->getProductIds());
    }

    public function testReturnsEmptyWithoutFunctionalConsent(): void
    {
        $request = Request::create('/');
        $request->cookies->set('symfoshop_cookie_consent', 'essential');
        $request->setSession($this->session);
        $stack = new RequestStack();
        $stack->push($request);

        $service = new RecentlyViewedService($stack, $this->productRepository);
        $service->addProduct($this->makeProduct(1));

        $this->assertSame([], $service->getProductIds());
    }

    private function makeProduct(int $id): Product
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn($id);
        return $product;
    }
}

