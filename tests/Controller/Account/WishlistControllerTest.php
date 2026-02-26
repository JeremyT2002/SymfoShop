<?php

namespace App\Tests\Controller\Account;

use App\Entity\Product;
use App\Entity\ProductStatus;
use App\Entity\User;
use App\Entity\Wishlist;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class WishlistControllerTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()->get('doctrine')->getManager();
    }

    public function testWishlistPageRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/account/wishlist');

        // Should redirect to login
        $this->assertResponseRedirects();
    }

    public function testWishlistPageShowsEmptyState(): void
    {
        $client = static::createClient();
        $user = $this->createTestUser();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/account/wishlist');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'My Wishlist');
    }

    public function testToggleAddsProductToWishlist(): void
    {
        $client = static::createClient();
        $user = $this->createTestUser();
        $product = $this->createTestProduct();
        $client->loginUser($user);

        // Toggle to add
        $client->request('POST', '/account/wishlist/toggle', [
            'productId' => $product->getId(),
        ]);

        $this->assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
        $this->assertTrue($response['inWishlist']);

        // Verify in database
        $wishlistRepo = $this->entityManager->getRepository(Wishlist::class);
        $this->assertTrue($wishlistRepo->isProductInWishlist($user, $product));
    }

    public function testToggleRemovesProductFromWishlist(): void
    {
        $client = static::createClient();
        $user = $this->createTestUser();
        $product = $this->createTestProduct();
        $client->loginUser($user);

        // Add to wishlist first
        $wishlist = new Wishlist();
        $wishlist->setUser($user);
        $wishlist->setProduct($product);
        $this->entityManager->persist($wishlist);
        $this->entityManager->flush();

        // Toggle to remove
        $client->request('POST', '/account/wishlist/toggle', [
            'productId' => $product->getId(),
        ]);

        $this->assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
        $this->assertFalse($response['inWishlist']);

        // Verify removed from database
        $wishlistRepo = $this->entityManager->getRepository(Wishlist::class);
        $this->assertFalse($wishlistRepo->isProductInWishlist($user, $product));
    }

    public function testTogglePreventsDuplicates(): void
    {
        $client = static::createClient();
        $user = $this->createTestUser();
        $product = $this->createTestProduct();
        $client->loginUser($user);

        // Add to wishlist
        $client->request('POST', '/account/wishlist/toggle', [
            'productId' => $product->getId(),
        ]);

        // Try to add again
        $client->request('POST', '/account/wishlist/toggle', [
            'productId' => $product->getId(),
        ]);

        // Should toggle off (remove)
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
        $this->assertFalse($response['inWishlist']);

        // Verify only one entry exists (now removed)
        $wishlistRepo = $this->entityManager->getRepository(Wishlist::class);
        $this->assertFalse($wishlistRepo->isProductInWishlist($user, $product));
    }

    public function testCheckEndpointReturnsWishlistStatus(): void
    {
        $client = static::createClient();
        $user = $this->createTestUser();
        $product = $this->createTestProduct();
        $client->loginUser($user);

        // Initially not in wishlist
        $client->request('GET', '/account/wishlist/check?productId=' . $product->getId());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
        $this->assertFalse($response['inWishlist']);

        // Add to wishlist
        $wishlist = new Wishlist();
        $wishlist->setUser($user);
        $wishlist->setProduct($product);
        $this->entityManager->persist($wishlist);
        $this->entityManager->flush();

        // Now should be in wishlist
        $client->request('GET', '/account/wishlist/check?productId=' . $product->getId());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
        $this->assertTrue($response['inWishlist']);
    }

    private function createTestUser(): User
    {
        $user = new User();
        $user->setEmail('test' . uniqid() . '@example.com');
        $user->setPassword('password');
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        return $user;
    }

    private function createTestProduct(string $name = 'Test Product', string $slug = null): Product
    {
        $slug = $slug ?? 'test-product-' . uniqid();
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

