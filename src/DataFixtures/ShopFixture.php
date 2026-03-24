<?php

namespace App\DataFixtures;

use App\Entity\Shop;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Default shop for single-tenant storefront (see ShopContextResolver::findDefault).
 */
class ShopFixture extends Fixture
{
    public const REFERENCE = 'shop_default';

    public function load(ObjectManager $manager): void
    {
        $shop = new Shop();
        $shop->setName('SymfoShop');
        $shop->setSlug('default');
        $shop->setIsActive(true);

        $manager->persist($shop);
        $manager->flush();

        $this->addReference(self::REFERENCE, $shop);
    }
}
