<?php

namespace App\DataFixtures;

use App\Entity\Shop;
use App\Entity\User;
use App\Theme\ThemeConfigService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Published default theme for the fixture shop (matches app:theme:init behavior).
 */
class ThemeFixture extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private readonly ThemeConfigService $themeConfig,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $shop = $this->getReference(ShopFixture::REFERENCE, Shop::class);
        $admin = $this->getReference('user_admin', User::class);

        $theme = $this->themeConfig->getOrCreateDraftTheme($shop);
        if ($theme->isDraft()) {
            $this->themeConfig->publish($theme, $admin);
        }
    }

    public function getDependencies(): array
    {
        return [
            ShopFixture::class,
            UserFixture::class,
        ];
    }
}
