<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Renders the admin pages (list and create screens) as an administrator.
 *
 * The audit log is left out: it hydrates entities other tests delete with raw DQL, and SQLite
 * (test DB) does not enforce the foreign keys that would cascade those deletes. The support inbox
 * depends on the theme's support provider and is covered by SupportFlowTest.
 */
final class AdminSmokeTest extends WebTestCase
{
    public function testAdminPagesRender(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get('doctrine')->getManager();
        \assert($em instanceof EntityManagerInterface);

        $admin = new User();
        $admin->setEmail('admin-smoke-' . uniqid('', true) . '@example.com');
        $admin->setPassword('password');
        $admin->setRoles(['ROLE_ADMIN']);
        $em->persist($admin);
        $em->flush();
        $client->loginUser($admin);

        foreach ([
            '/admin', '/admin?period=7d', '/admin/products', '/admin/products/new', '/admin/categories', '/admin/categories/new',
            '/admin/orders', '/admin/return-requests', '/admin/coupons', '/admin/coupons/new', '/admin/users', '/admin/users/new',
            '/admin/reviews', '/admin/settings', '/admin/theme', '/admin/payment-methods', '/admin/payment-methods/new',
            '/admin/shipping-methods', '/admin/shipping-methods/new', '/admin/api-keys', '/admin/api-keys/new',
            '/admin/api-docs', '/admin/search?q=test',
        ] as $url) {
            $client->request('GET', $url);
            self::assertResponseIsSuccessful(sprintf('GET %s failed', $url));
        }

        $client->request('GET', '/admin');
        self::assertSelectorExists('aside nav a[aria-current="page"]');
        self::assertSelectorExists('#dashboard-chart-data');
    }
}
