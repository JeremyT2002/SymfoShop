<?php

namespace App\Tests\Repository;

use App\Entity\Shop;
use App\Repository\ShopRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ShopRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ShopRepository $repo;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get('doctrine')->getManager();
        $this->repo = $this->em->getRepository(Shop::class);
    }

    public function testFindBySlugAndFindDefault(): void
    {
        $s = new Shop();
        $s->setName('Test Shop');
        $s->setSlug('shop-' . uniqid());
        $this->em->persist($s);
        $this->em->flush();

        $this->assertSame($s->getId(), $this->repo->findBySlug($s->getSlug())?->getId());
        $this->assertNotNull($this->repo->findDefault());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }
}
