<?php

namespace App\DataFixtures;

use App\Entity\Category;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class CategoryFixture extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Categories
        $electronics = $this->createCategory('Electronics', 'electronics');
        $clothing = $this->createCategory('Clothing', 'clothing');
        $books = $this->createCategory('Books', 'books');
        $home = $this->createCategory('Home & Garden', 'home-garden');
        $sports = $this->createCategory('Sports & Outdoors', 'sports-outdoors');

        $manager->persist($electronics);
        $manager->persist($clothing);
        $manager->persist($books);
        $manager->persist($home);
        $manager->persist($sports);

        $this->addReference('category_electronics', $electronics);
        $this->addReference('category_clothing', $clothing);
        $this->addReference('category_books', $books);
        $this->addReference('category_home', $home);
        $this->addReference('category_sports', $sports);

        $manager->flush();
    }

    private function createCategory(string $name, string $slug): Category
    {
        $category = new Category();
        $category->setName($name);
        $category->setSlug($slug);
        return $category;
    }
}

