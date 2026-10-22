<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use OTPHP\TOTP;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class StaffAccessTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = $this->client->getContainer()->get('doctrine')->getManager();
    }

    public function testSupportStaffSeesOnlyTheirSections(): void
    {
        $this->client->loginUser($this->createUser(['ROLE_SUPPORT']));

        $this->client->request('GET', '/admin');
        self::assertResponseRedirects('/admin/orders');

        $crawler = $this->client->request('GET', '/admin/orders');
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('aside a[href="/admin/products"]'), 'no products in the navigation');
        self::assertCount(1, $crawler->filter('aside a[href="/admin/support"]'));

        // The inbox redirects to the orders while the support module is switched off
        $this->client->request('GET', '/admin/support');
        self::assertTrue($this->client->getResponse()->isSuccessful() || $this->client->getResponse()->isRedirect('/admin/orders'));

        foreach (['/admin/return-requests', '/admin/reviews', '/admin/two-factor'] as $allowed) {
            $this->client->request('GET', $allowed);
            self::assertResponseIsSuccessful($allowed);
        }
        $this->client->request('POST', '/admin/support-provider');
        self::assertResponseStatusCodeSame(403);
        foreach (['/admin/products', '/admin/categories', '/admin/settings', '/admin/users', '/admin/search?q=x'] as $denied) {
            $this->client->request('GET', $denied);
            self::assertResponseStatusCodeSame(403, $denied);
        }
    }

    public function testWarehouseStaffCannotRefund(): void
    {
        $this->client->loginUser($this->createUser(['ROLE_WAREHOUSE']));

        foreach (['/admin/orders', '/admin/products', '/admin/products/import', '/admin/categories'] as $allowed) {
            $this->client->request('GET', $allowed);
            self::assertResponseIsSuccessful($allowed);
        }
        $this->client->request('GET', '/admin/support');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/admin/orders/1/refund');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminAssignsOnlyKnownRoles(): void
    {
        $admin = $this->createUser(['ROLE_ADMIN']);
        $staff = $this->createUser([]);
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/admin/users/' . $staff->getId() . '/edit');
        $form = $crawler->filter('form[method="post"]')->reduce(static fn ($f): bool => $f->filter('input[name="roles[]"]')->count() > 0)->form();
        $values = $form->getPhpValues();
        $values['roles'] = ['ROLE_WAREHOUSE', 'ROLE_SUPER_ADMIN'];
        $this->client->request('POST', $form->getUri(), $values);

        $this->em->clear();
        self::assertSame(['ROLE_WAREHOUSE', 'ROLE_USER'], $this->em->find(User::class, $staff->getId())->getRoles());
    }

    public function testSetUpTwoFactorAndLogInWithCode(): void
    {
        $password = 'correct horse battery';
        $admin = $this->createUser(['ROLE_ADMIN'], $password);
        $this->client->loginUser($admin);

        // Wrong code: stays off
        $crawler = $this->client->request('GET', '/admin/two-factor');
        $secret = str_replace(' ', '', $crawler->filter('.select-all')->text());
        $this->submitCode($crawler, 'two_factor_enable', '000000');
        self::assertFalse($this->reload($admin)->isTotpAuthenticationEnabled());

        $crawler = $this->client->request('GET', '/admin/two-factor');
        self::assertSame($secret, str_replace(' ', '', $crawler->filter('.select-all')->text()), 'same secret until confirmed');
        $this->submitCode($crawler, 'two_factor_enable', TOTP::createFromSecret($secret)->now());
        self::assertTrue($this->reload($admin)->isTotpAuthenticationEnabled());

        // Fresh login: password alone is not enough
        $this->client->request('GET', '/logout');
        $this->client->restart();
        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->filter('form[method="post"]')->form([
            '_username' => $admin->getEmail(),
            '_password' => $password,
        ]));
        $this->client->followRedirect();
        self::assertSame('/2fa', parse_url((string) $this->client->getRequest()->getUri(), \PHP_URL_PATH));

        $this->client->request('GET', '/admin/orders');
        self::assertResponseRedirects();
        self::assertStringEndsWith('/2fa', (string) $this->client->getResponse()->headers->get('Location'));

        $crawler = $this->client->request('GET', '/2fa');
        $this->client->submit($crawler->filter('form[action$="/2fa_check"]')->form(['_auth_code' => TOTP::createFromSecret($secret)->now()]));
        $this->client->request('GET', '/admin/orders');
        self::assertResponseIsSuccessful();
    }

    public function testRequiredTwoFactorRedirectsStaffToSetup(): void
    {
        $_SERVER['ADMIN_REQUIRE_2FA'] = $_ENV['ADMIN_REQUIRE_2FA'] = '1';
        try {
            self::ensureKernelShutdown();
            $client = static::createClient();
            $em = $client->getContainer()->get('doctrine')->getManager();
            $user = (new User())->setEmail('require-2fa-' . uniqid() . '@example.com')->setPassword('x')->setRoles(['ROLE_WAREHOUSE']);
            $em->persist($user);
            $em->flush();
            $client->loginUser($user);

            $client->request('GET', '/admin/products');
            self::assertResponseRedirects('/admin/two-factor');
            $client->request('GET', '/admin/two-factor');
            self::assertResponseIsSuccessful();
        } finally {
            unset($_SERVER['ADMIN_REQUIRE_2FA'], $_ENV['ADMIN_REQUIRE_2FA']);
        }
    }

    private function submitCode(\Symfony\Component\DomCrawler\Crawler $crawler, string $form, string $code): void
    {
        $action = $form === 'two_factor_enable' ? '/admin/two-factor/enable' : '/admin/two-factor/disable';
        $this->client->submit($crawler->filter('form[action="' . $action . '"]')->form(['code' => $code]));
        self::assertResponseRedirects('/admin/two-factor');
    }

    /**
     * @param list<string> $roles
     */
    private function createUser(array $roles, string $password = 'x'): User
    {
        $user = (new User())->setEmail('staff-' . uniqid() . '@example.com')->setRoles($roles)->setIsVerified(true);
        $hasher = $this->client->getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPassword($hasher->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function reload(User $user): User
    {
        $this->em->clear();

        return $this->em->find(User::class, $user->getId());
    }
}
