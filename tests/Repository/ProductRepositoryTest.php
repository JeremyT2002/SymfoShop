<?php

namespace App\Tests\Repository;

use App\Catalog\CatalogFilters;
use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductStatus;
use App\Entity\ProductVariant;
use App\Entity\StockItem;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ProductRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ProductRepository $repo;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get('doctrine')->getManager();
        $this->repo = $this->em->getRepository(Product::class);
    }

    public function testFindOneBySlugReturnsOnlyActive(): void
    {
        $cat = $this->newCategory();
        $active = $this->newProduct($cat, 'active-' . uniqid(), ProductStatus::ACTIVE);
        $this->em->persist($active);
        $this->em->flush();

        $this->assertSame($active->getId(), $this->repo->findOneBySlug($active->getSlug())?->getId());

        $active->setStatus(ProductStatus::DRAFT);
        $this->em->flush();

        $this->assertNull($this->repo->findOneBySlug($active->getSlug()));
    }

    public function testFindActiveProductsPaginationAndCount(): void
    {
        $cat = $this->newCategory();
        for ($i = 0; $i < 3; ++$i) {
            $p = $this->newProduct($cat, 'pag-' . $i . '-' . uniqid(), ProductStatus::ACTIVE);
            $p->setCreatedAt(new \DateTimeImmutable(sprintf('2020-01-%02d', $i + 1)));
            $this->addVariant($p, 1000 + $i);
            $this->em->persist($p);
        }
        $this->em->flush();

        $this->assertGreaterThanOrEqual(3, $this->repo->countActiveProducts());
        $page = $this->repo->findActiveProducts(0, 2);
        $this->assertCount(2, $page);
    }

    public function testFindFilteredByCategoryPriceAndInStock(): void
    {
        $cat = $this->newCategory();
        $cheap = $this->newProduct($cat, 'cheap-' . uniqid(), ProductStatus::ACTIVE);
        $this->addVariant($cheap, 500);
        $this->addStock($this->getSingleVariant($cheap), 10, 0);

        $expensive = $this->newProduct($cat, 'expensive-' . uniqid(), ProductStatus::ACTIVE);
        $this->addVariant($expensive, 5000);
        $this->addStock($this->getSingleVariant($expensive), 1, 0);

        $noStock = $this->newProduct($cat, 'nostock-' . uniqid(), ProductStatus::ACTIVE);
        $this->addVariant($noStock, 800);
        $this->em->persist($cheap);
        $this->em->persist($expensive);
        $this->em->persist($noStock);
        $this->em->flush();

        $filters = new CatalogFilters(minPrice: 400, maxPrice: 900, inStockOnly: true);
        $found = $this->repo->findFilteredByCategory($cat, $filters);
        $ids = array_map(fn (Product $p) => $p->getId(), $found);

        $this->assertContains($cheap->getId(), $ids);
        $this->assertNotContains($expensive->getId(), $ids);
        $this->assertNotContains($noStock->getId(), $ids);

        $this->assertSame(count($ids), $this->repo->countFilteredByCategory($cat, $filters));
    }

    public function testFindFilteredByCategoryAttributeFilter(): void
    {
        $cat = $this->newCategory();
        $match = $this->newProduct($cat, 'match-' . uniqid(), ProductStatus::ACTIVE);
        $v = $this->addVariant($match, 1000);
        $v->setAttributes(['color' => 'Red']);

        $other = $this->newProduct($cat, 'other-' . uniqid(), ProductStatus::ACTIVE);
        $v2 = $this->addVariant($other, 1000);
        $v2->setAttributes(['color' => 'Blue']);

        $this->em->persist($match);
        $this->em->persist($other);
        $this->em->flush();

        $filters = new CatalogFilters(attributeFilters: ['color' => ['Red']]);
        $found = $this->repo->findFilteredByCategory($cat, $filters);

        $this->assertCount(1, $found);
        $this->assertSame($match->getId(), $found[0]->getId());
    }

    public function testFindFilteredByCategoryImpossibleAttributeReturnsEmpty(): void
    {
        $cat = $this->newCategory();
        $p = $this->newProduct($cat, 'solo-' . uniqid(), ProductStatus::ACTIVE);
        $this->addVariant($p, 1000)->setAttributes(['color' => 'Red']);
        $this->em->persist($p);
        $this->em->flush();

        $filters = new CatalogFilters(attributeFilters: ['color' => ['NonexistentColor']]);
        $this->assertSame([], $this->repo->findFilteredByCategory($cat, $filters));
    }

    public function testSortingByNameAndPrice(): void
    {
        $cat = $this->newCategory();
        $b = $this->newProduct($cat, 'sort-b-' . uniqid(), ProductStatus::ACTIVE);
        $b->setName('Bravo');
        $this->addVariant($b, 2000);

        $a = $this->newProduct($cat, 'sort-a-' . uniqid(), ProductStatus::ACTIVE);
        $a->setName('Alpha');
        $this->addVariant($a, 3000);

        $this->em->persist($b);
        $this->em->persist($a);
        $this->em->flush();

        $byName = $this->repo->findFilteredByCategory($cat, new CatalogFilters(sort: 'name-asc'));
        $this->assertSame('Alpha', $byName[0]->getName());

        $byPriceAsc = $this->repo->findFilteredByCategory($cat, new CatalogFilters(sort: 'price-asc'));
        $this->assertSame('Bravo', $byPriceAsc[0]->getName());

        $byPriceDesc = $this->repo->findFilteredByCategory($cat, new CatalogFilters(sort: 'price-desc'));
        $this->assertSame('Alpha', $byPriceDesc[0]->getName());

        $byNameDesc = $this->repo->findFilteredByCategory($cat, new CatalogFilters(sort: 'name-desc'));
        $this->assertSame('Bravo', $byNameDesc[0]->getName());

        $defaultSort = $this->repo->findFilteredByCategory($cat, new CatalogFilters(sort: 'default'));
        $this->assertCount(2, $defaultSort);
    }

    public function testGetFilterOptionsForCategory(): void
    {
        $cat = $this->newCategory();
        $p = $this->newProduct($cat, 'opt-' . uniqid(), ProductStatus::ACTIVE);
        $this->addVariant($p, 1500)->setAttributes(['size' => 'M']);
        $this->addVariant($p, 2500)->setAttributes(['size' => 'L']);
        $this->em->persist($p);
        $this->em->flush();

        $opts = $this->repo->getFilterOptionsForCategory($cat);
        $this->assertSame(1500, $opts['price_min']);
        $this->assertSame(2500, $opts['price_max']);
        $this->assertArrayHasKey('size', $opts['attributes']);
        $this->assertSame(1, $opts['attributes']['size']['M']);
        $this->assertSame(1, $opts['attributes']['size']['L']);
    }

    private function newCategory(): Category
    {
        $c = new Category();
        $c->setName('Cat ' . uniqid());
        $c->setSlug('cat-' . uniqid());
        $this->em->persist($c);
        $this->em->flush();

        return $c;
    }

    private function newProduct(Category $cat, string $slug, ProductStatus $status): Product
    {
        $p = new Product();
        $p->setName('Product ' . $slug);
        $p->setSlug($slug);
        $p->setStatus($status);
        $p->setTaxClass('standard');
        $p->setCategory($cat);

        return $p;
    }

    private function addVariant(Product $product, int $priceCents): ProductVariant
    {
        $v = new ProductVariant();
        $v->setSku('SKU-' . uniqid());
        $v->setPriceAmount($priceCents);
        $v->setCurrency('EUR');
        $v->setAttributes([]);
        $product->addVariant($v);
        $this->em->persist($v);

        return $v;
    }

    private function getSingleVariant(Product $product): ProductVariant
    {
        $v = $product->getVariants()->first();
        if (!$v instanceof ProductVariant) {
            throw new \RuntimeException('Expected variant');
        }

        return $v;
    }

    private function addStock(ProductVariant $variant, int $onHand, int $reserved): void
    {
        $s = new StockItem();
        $s->setVariant($variant);
        $s->setOnHand($onHand);
        $s->setReserved($reserved);
        $this->em->persist($s);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }
}
