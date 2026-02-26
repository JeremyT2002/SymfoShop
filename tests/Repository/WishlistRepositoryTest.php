<?php

namespace App\Tests\Repository;

use App\Entity\Product;
use App\Entity\ProductStatus;
use App\Entity\User;
use App\Entity\Wishlist;
use App\Repository\WishlistRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class WishlistRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private WishlistRepository $wishlistRepository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()->get('doctrine')->getManager();
        $this->wishlistRepository = $this->entityManager->getRepository(Wishlist::class);
    }

    public function testIsProductInWishlist(): void
    {
        // Create test user and product
        $user = $this->createTestUser();
        $product = $this->createTestProduct();

        // Initially not in wishlist
        $this->assertFalse($this->wishlistRepository->isProductInWishlist($user, $product));

        // Add to wishlist
        $wishlist = new Wishlist();
        $wishlist->setUser($user);
        $wishlist->setProduct($product);
        $this->entityManager->persist($wishlist);
        $this->entityManager->flush();

        // Now should be in wishlist
        $this->assertTrue($this->wishlistRepository->isProductInWishlist($user, $product));
    }

    public function testFindOneByUserAndProduct(): void
    {
        $user = $this->createTestUser();
        $product = $this->createTestProduct();

        // Initially null
        $this->assertNull($this->wishlistRepository->findOneByUserAndProduct($user, $product));

        // Add to wishlist
        $wishlist = new Wishlist();
        $wishlist->setUser($user);
        $wishlist->setProduct($product);
        $this->entityManager->persist($wishlist);
        $this->entityManager->flush();

        // Should find the wishlist item
        $found = $this->wishlistRepository->findOneByUserAndProduct($user, $product);
        $this->assertNotNull($found);
        $this->assertEquals($user->getId(), $found->getUser()->getId());
        $this->assertEquals($product->getId(), $found->getProduct()->getId());
    }

    public function testFindByUser(): void
    {
        $user = $this->createTestUser();
        $product1 = $this->createTestProduct('Product 1', 'product-1');
        $product2 = $this->createTestProduct('Product 2', 'product-2');

        // Initially empty
        $this->assertCount(0, $this->wishlistRepository->findByUser($user));

        // Add products to wishlist
        $wishlist1 = new Wishlist();
        $wishlist1->setUser($user);
        $wishlist1->setProduct($product1);
        $this->entityManager->persist($wishlist1);

        $wishlist2 = new Wishlist();
        $wishlist2->setUser($user);
        $wishlist2->setProduct($product2);
        $this->entityManager->persist($wishlist2);
        $this->entityManager->flush();

        // Should find both items
        $items = $this->wishlistRepository->findByUser($user);
        $this->assertCount(2, $items);
    }

    public function testCountByUser(): void
    {
        $user = $this->createTestUser();
        $product = $this->createTestProduct();

        // Initially zero
        $this->assertEquals(0, $this->wishlistRepository->countByUser($user));

        // Add to wishlist
        $wishlist = new Wishlist();
        $wishlist->setUser($user);
        $wishlist->setProduct($product);
        $this->entityManager->persist($wishlist);
        $this->entityManager->flush();

        // Should be 1
        $this->assertEquals(1, $this->wishlistRepository->countByUser($user));
    }

    private function createTestUser(): User
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPassword('password');
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        return $user;
    }

    private function createTestProduct(string $name = 'Test Product', string $slug = 'test-product'): Product
    {
        $product = new Product();
        $product->setName($name);
        $product->setSlug($slug);
        $product->setStatus(ProductStatus::ACTIVE);
        $product->setTaxClass('standard');
        $this->entityManager->persist($product);
        $this->entityManager->flush();
        return $product;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }
}

