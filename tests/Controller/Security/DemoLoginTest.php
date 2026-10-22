<?php

declare(strict_types=1);

namespace App\Tests\Controller\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class DemoLoginTest extends WebTestCase
{
    protected function tearDown(): void
    {
        self::setDemoMode(false);
        parent::tearDown();
    }

    public function testLoginPageHasNoDemoButtonsByDefault(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-testid="demo-login"]');

        $client->request('POST', '/demo-login/admin');
        self::assertResponseStatusCodeSame(404);
    }

    public function testAdminButtonSignsInAndOpensTheBackOffice(): void
    {
        self::setDemoMode(true);
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        self::assertSelectorExists('[data-testid="demo-login"]');
        self::assertCount(2, $crawler->filter('[data-testid="demo-login"] form'));

        $client->submit($crawler->filter('form[action="/demo-login/admin"]')->form());
        self::assertResponseRedirects('/admin');

        $client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    public function testCustomerButtonSignsInAndOpensTheAccount(): void
    {
        self::setDemoMode(true);
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        $client->submit($crawler->filter('form[action="/demo-login/customer"]')->form());
        self::assertResponseRedirects('/account');

        $client->request('GET', '/admin');
        self::assertResponseStatusCodeSame(403);
    }

    public function testDemoLoginRequiresAValidCsrfToken(): void
    {
        self::setDemoMode(true);
        $client = static::createClient();
        $client->request('POST', '/demo-login/admin', ['_csrf_token' => 'forged']);

        // Access denied for an anonymous visitor → back to the login page, nobody is signed in
        self::assertResponseRedirects('/login');
        $client->request('GET', '/account');
        self::assertResponseRedirects('/login');
    }

    public function testUnknownDemoAccountIsNotFound(): void
    {
        self::setDemoMode(true);
        $client = static::createClient();
        $client->request('POST', '/demo-login/root');

        self::assertResponseStatusCodeSame(404);
    }

    public function testVisitorsCannotLockOthersOutOfTheDemoAdmin(): void
    {
        self::setDemoMode(true);
        $client = static::createClient();
        $em = $this->em($client);

        // Someone signed in as the demo admin, changed the password, turned on 2FA and deactivated the account
        $crawler = $client->request('GET', '/login');
        $client->submit($crawler->filter('form[action="/demo-login/admin"]')->form());
        $admin = $em->getRepository(User::class)->findOneBy(['email' => 'admin@symfoshop.com']);
        self::assertInstanceOf(User::class, $admin);
        $admin->setPassword('changed-by-a-visitor')->setTotpSecret('JBSWY3DPEHPK3PXP')->setIsActive(false);
        $em->flush();

        $client->request('GET', '/logout');
        $crawler = $client->request('GET', '/login');
        $client->submit($crawler->filter('form[action="/demo-login/admin"]')->form());

        self::assertResponseRedirects('/admin');
        $em->clear();
        $admin = $em->getRepository(User::class)->findOneBy(['email' => 'admin@symfoshop.com']);
        self::assertInstanceOf(User::class, $admin);
        self::assertFalse($admin->isTotpAuthenticationEnabled());
        self::assertTrue($admin->isActive());
    }

    public function testCommandRefusesToRunWithoutDemoMode(): void
    {
        self::bootKernel();
        $tester = new CommandTester((new Application(self::$kernel))->find('app:demo:accounts'));

        self::assertSame(1, $tester->execute([]));
        self::assertStringContainsString('DEMO_MODE is off', $tester->getDisplay());
    }

    public function testCommandCreatesTheDemoAccounts(): void
    {
        self::setDemoMode(true);
        self::bootKernel();
        $tester = new CommandTester((new Application(self::$kernel))->find('app:demo:accounts'));

        self::assertSame(0, $tester->execute([]));

        $users = self::getContainer()->get(EntityManagerInterface::class)->getRepository(User::class);
        $customer = $users->findOneBy(['email' => 'john.doe@example.com']);
        self::assertInstanceOf(User::class, $customer);
        self::assertSame(['ROLE_USER'], $customer->getRoles());
        self::assertTrue($customer->isVerified());
    }

    private static function setDemoMode(bool $enabled): void
    {
        if ($enabled) {
            $_ENV['DEMO_MODE'] = $_SERVER['DEMO_MODE'] = '1';
        } else {
            unset($_ENV['DEMO_MODE'], $_SERVER['DEMO_MODE']);
        }
    }

    private function em(KernelBrowser $client): EntityManagerInterface
    {
        return $client->getContainer()->get('doctrine')->getManager();
    }
}
