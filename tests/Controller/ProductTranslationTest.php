<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductStatus;
use App\Entity\ProductVariant;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProductTranslationTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = $this->client->getContainer()->get('doctrine')->getManager();
    }

    public function testProductPageShowsTranslationAndHreflangAlternates(): void
    {
        $product = $this->createProduct();

        $crawler = $this->client->request('GET', '/product/' . $product->getSlug() . '?lang=de');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', 'Kabellose Kopfhörer');
        self::assertSelectorTextContains('main', 'Satter Klang');
        self::assertSame('de', $crawler->filter('html')->attr('lang'));

        $base = 'http://localhost/product/' . $product->getSlug();
        self::assertSame($base . '?lang=de', $crawler->filter('link[rel="canonical"]')->attr('href'));
        self::assertSame($base, $crawler->filter('link[hreflang="en"]')->attr('href'));
        self::assertSame($base . '?lang=fr', $crawler->filter('link[hreflang="fr"]')->attr('href'));
        self::assertSame($base, $crawler->filter('link[hreflang="x-default"]')->attr('href'));

        // No French translation: English text
        $this->client->request('GET', '/product/' . $product->getSlug() . '?lang=fr');
        self::assertSelectorTextSame('h1', 'Wireless Headphones');
    }

    public function testSearchSuggestionsUseTranslatedName(): void
    {
        $this->createProduct();

        $this->client->request('GET', '/search/suggest?q=Kopfh%C3%B6rer&lang=de');
        self::assertResponseIsSuccessful();
        $names = array_column(json_decode((string) $this->client->getResponse()->getContent(), true)['products'], 'name');
        self::assertContains('Kabellose Kopfhörer', $names);
    }

    public function testLanguageSwitchDropsLangParameter(): void
    {
        $product = $this->createProduct();
        $url = 'http://localhost/product/' . $product->getSlug() . '?lang=de&page=2';

        $this->client->request('GET', '/locale/fr', server: ['HTTP_REFERER' => $url]);
        self::assertResponseRedirects('http://localhost/product/' . $product->getSlug() . '?page=2');
    }

    public function testSitemapListsLanguageVersions(): void
    {
        $product = $this->createProduct();

        $this->client->request('GET', '/sitemap.xml');
        self::assertResponseIsSuccessful();
        $xml = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('<loc>http://localhost/product/' . $product->getSlug() . '?lang=de</loc>', $xml);
        self::assertStringContainsString('hreflang="x-default" href="http://localhost/product/' . $product->getSlug() . '"', $xml);
    }

    public function testAdminSavesTranslations(): void
    {
        $product = $this->createProduct();
        $admin = (new User())->setEmail('i18n-admin-' . uniqid() . '@example.com')->setPassword('x')->setRoles(['ROLE_ADMIN']);
        $this->em->persist($admin);
        $this->em->flush();
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/admin/products/' . $product->getId() . '/edit');
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form')->reduce(static fn ($f): bool => $f->filter('input[name="translations[fr][name]"]')->count() > 0)->form();
        $form['translations[fr][name]'] = 'Casque sans fil';
        $form['translations[de][name]'] = '';
        $this->client->submit($form);

        $this->em->clear();
        $saved = $this->em->getRepository(Product::class)->find($product->getId());
        self::assertSame('Casque sans fil', $saved->translationOf('fr', 'name'));
        self::assertNull($saved->translationOf('de', 'name'));
        self::assertSame('Satter Klang', $saved->translationOf('de', 'description'));
    }

    private function createProduct(): Product
    {
        $suffix = uniqid();
        $category = (new Category())->setName('Audio ' . $suffix)->setSlug('audio-' . $suffix);
        $category->putTranslation('de', 'name', 'Audio DE ' . $suffix);
        $this->em->persist($category);

        $product = (new Product())
            ->setName('Wireless Headphones')
            ->setSlug('wireless-headphones-' . $suffix)
            ->setDescription('Rich sound')
            ->setStatus(ProductStatus::ACTIVE)
            ->setTaxClass('standard')
            ->setCategory($category);
        $product->setTranslations(['de' => ['name' => 'Kabellose Kopfhörer', 'description' => 'Satter Klang']]);
        $this->em->persist($product);

        $variant = (new ProductVariant())->setProduct($product)->setSku('I18N-' . $suffix)->setPriceAmount(4999)->setCurrency('EUR');
        $product->addVariant($variant);
        $this->em->persist($variant);
        $this->em->flush();

        return $product;
    }
}
