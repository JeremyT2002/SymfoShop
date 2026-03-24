<?php

namespace App\Tests\Repository;

use App\Entity\Shop;
use App\Entity\Theme;
use App\Repository\ThemeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ThemeRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ThemeRepository $repo;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get('doctrine')->getManager();
        $this->repo = static::getContainer()->get(ThemeRepository::class);
    }

    public function testFindByShopAndSlugFindByShopFindPublished(): void
    {
        $shop = new Shop();
        $shop->setName('S');
        $shop->setSlug('s-' . uniqid());
        $this->em->persist($shop);

        $draft = new Theme();
        $draft->setShop($shop);
        $draft->setName('Draft');
        $draft->setSlug('main');
        $draft->setStatus(Theme::STATUS_DRAFT);
        $this->em->persist($draft);

        $published = new Theme();
        $published->setShop($shop);
        $published->setName('Live');
        $published->setSlug('live');
        $published->setStatus(Theme::STATUS_PUBLISHED);
        $this->em->persist($published);
        $this->em->flush();

        $this->assertSame($draft->getId(), $this->repo->findByShopAndSlug($shop, 'main')?->getId());
        $byShop = $this->repo->findByShop($shop);
        $ids = array_map(fn (Theme $x) => $x->getId(), $byShop);
        $this->assertContains($draft->getId(), $ids);
        $this->assertContains($published->getId(), $ids);

        $this->assertSame($published->getId(), $this->repo->findPublishedByShop($shop)?->getId());
    }

    public function testFindWithNullShop(): void
    {
        $t = new Theme();
        $t->setShop(null);
        $t->setName('Global');
        $t->setSlug('g-' . uniqid());
        $this->em->persist($t);
        $this->em->flush();

        $this->assertSame($t->getId(), $this->repo->findByShopAndSlug(null, $t->getSlug())?->getId());
        $this->assertContains($t->getId(), array_map(fn (Theme $x) => $x->getId(), $this->repo->findByShop(null)));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }
}
