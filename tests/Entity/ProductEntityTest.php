<?php

namespace App\Tests\Entity;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductMedia;
use App\Entity\ProductStatus;
use App\Entity\ProductVariant;
use PHPUnit\Framework\TestCase;

final class ProductEntityTest extends TestCase
{
    public function testVariantsAndCategory(): void
    {
        $p = new Product();
        $p->setName('N');
        $p->setSlug('s');
        $p->setStatus(ProductStatus::ACTIVE);
        $v = new ProductVariant();
        $v->setSku('sku');
        $v->setPriceAmount(1);
        $v->setCurrency('EUR');
        $v->setAttributes([]);
        $p->addVariant($v);
        $this->assertSame($p, $v->getProduct());
        $this->assertCount(1, $p->getVariants());

        $cat = new Category();
        $cat->setName('C');
        $cat->setSlug('c');
        $p->setCategory($cat);
        $this->assertSame($cat, $p->getCategory());
        $p->setCategory(null);
        $this->assertNull($p->getCategory());
    }

    public function testMediaLifecycle(): void
    {
        $p = new Product();
        $p->setName('P');
        $p->setSlug('p');
        $p->setStatus(ProductStatus::ACTIVE);
        $m = new ProductMedia();
        $m->setPath('/x.jpg');
        $m->setAlt('a');
        $m->setSort(2);
        $p->addMedia($m);
        $this->assertSame($p, $m->getProduct());
        $this->assertCount(1, $p->getMedia());
    }

    public function testDescriptionAndTaxClassAndTimestamps(): void
    {
        $p = new Product();
        $p->setName('T');
        $p->setSlug('t');
        $p->setStatus(ProductStatus::DRAFT);
        $p->setDescription('D');
        $p->setTaxClass('reduced');
        $this->assertSame('D', $p->getDescription());
        $this->assertSame('reduced', $p->getTaxClass());

        $u = new \DateTimeImmutable('2020-01-02');
        $p->setCreatedAt($u);
        $p->setUpdatedAt($u);
        $this->assertEquals($u, $p->getCreatedAt());
        $this->assertEquals($u, $p->getUpdatedAt());
    }
}
