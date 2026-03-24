<?php

namespace App\Tests\Repository;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CategoryRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private CategoryRepository $repo;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get('doctrine')->getManager();
        $this->repo = $this->em->getRepository(Category::class);
    }

    public function testFindOneBySlug(): void
    {
        $c = new Category();
        $c->setName('N');
        $c->setSlug('slug-' . uniqid());
        $this->em->persist($c);
        $this->em->flush();

        $this->assertSame($c->getId(), $this->repo->findOneBySlug($c->getSlug())?->getId());
    }

    public function testFindRootCategories(): void
    {
        $root = new Category();
        $root->setName('Root');
        $root->setSlug('root-' . uniqid());
        $child = new Category();
        $child->setName('Child');
        $child->setSlug('child-' . uniqid());
        $child->setParent($root);
        $this->em->persist($root);
        $this->em->persist($child);
        $this->em->flush();

        $roots = $this->repo->findRootCategories();
        $ids = array_map(fn (Category $x) => $x->getId(), $roots);
        $this->assertContains($root->getId(), $ids);
        $this->assertNotContains($child->getId(), $ids);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }
}
