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
        // Root categories
        $electronics = $this->createCategory('Electronics', 'electronics', null);
        $clothing = $this->createCategory('Clothing', 'clothing', null);
        $books = $this->createCategory('Books', 'books', null);
        $home = $this->createCategory('Home & Garden', 'home-garden', null);
        $sports = $this->createCategory('Sports & Outdoors', 'sports-outdoors', null);

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

        // Subcategories for Electronics
        $smartphones = $this->createCategory('Smartphones', 'smartphones', $electronics);
        $laptops = $this->createCategory('Laptops', 'laptops', $electronics);
        $tablets = $this->createCategory('Tablets', 'tablets', $electronics);
        $headphones = $this->createCategory('Headphones', 'headphones', $electronics);

        $manager->persist($smartphones);
        $manager->persist($laptops);
        $manager->persist($tablets);
        $manager->persist($headphones);

        $this->addReference('category_smartphones', $smartphones);
        $this->addReference('category_laptops', $laptops);
        $this->addReference('category_headphones', $headphones);

        // Subcategories for Clothing
        $mens = $this->createCategory('Men\'s Clothing', 'mens-clothing', $clothing);
        $womens = $this->createCategory('Women\'s Clothing', 'womens-clothing', $clothing);
        $shoes = $this->createCategory('Shoes', 'shoes', $clothing);

        $manager->persist($mens);
        $manager->persist($womens);
        $manager->persist($shoes);

        $this->addReference('category_mens', $mens);
        $this->addReference('category_womens', $womens);
        $this->addReference('category_shoes', $shoes);

        // Subcategories for Books
        $fiction = $this->createCategory('Fiction', 'fiction', $books);
        $nonFiction = $this->createCategory('Non-Fiction', 'non-fiction', $books);
        $techBooks = $this->createCategory('Technology', 'technology-books', $books);

        $manager->persist($fiction);
        $manager->persist($nonFiction);
        $manager->persist($techBooks);

        $this->addReference('category_fiction', $fiction);
        $this->addReference('category_technology', $techBooks);

        $manager->flush();
    }

    private function createCategory(string $name, string $slug, ?Category $parent): Category
    {
        $category = new Category();
        $category->setName($name);
        $category->setSlug($slug);
        if ($parent) {
            $category->setParent($parent);
        }
        return $category;
    }
}

