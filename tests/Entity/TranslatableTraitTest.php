<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Category;
use App\Entity\Product;
use PHPUnit\Framework\TestCase;

final class TranslatableTraitTest extends TestCase
{
    public function testFallsBackToDefaultLocaleText(): void
    {
        $product = (new Product())->setName('Headphones')->setDescription('Great sound')->setSeoTitle('Buy headphones');
        $product->putTranslation('de', 'name', 'Kopfhörer');

        self::assertSame('Kopfhörer', $product->translated('name', 'de'));
        self::assertSame('Great sound', $product->translated('description', 'de'));
        self::assertSame('Headphones', $product->translated('name', 'fr'));
        self::assertSame('Headphones', $product->translated('name', null));
        self::assertSame('Buy headphones', $product->translated('seoTitle', 'fr'));
        // Translated product without a translated SEO title: the template falls back to the German name
        self::assertNull($product->translated('seoTitle', 'de'));
    }

    public function testEmptyValuesAreDropped(): void
    {
        $category = (new Category())->setName('Audio');
        $category->setTranslations([
            'de' => ['name' => '  Audio & Musik ', 'seoTitle' => ''],
            'fr' => ['name' => '', 'seoTitle' => '   '],
        ]);

        self::assertSame(['de' => ['name' => 'Audio & Musik']], $category->getTranslations());

        $category->putTranslation('de', 'name', null);
        self::assertSame([], $category->getTranslations());
    }

    public function testUnknownFieldIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Category())->setName('Audio')->translated('slug', 'de');
    }
}
