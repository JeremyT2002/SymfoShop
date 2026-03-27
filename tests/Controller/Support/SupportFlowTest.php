<?php

declare(strict_types=1);

namespace App\Tests\Controller\Support;

use App\Entity\SupportConversation;
use App\Entity\User;
use App\Repository\SupportConversationRepository;
use App\Theme\ShopContextResolver;
use App\Theme\ThemeConfigService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SupportFlowTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $client = static::createClient();
        $this->entityManager = $client->getContainer()->get('doctrine')->getManager();
        $this->ensureSelfcodedSupportProvider($client->getContainer()->get(ThemeConfigService::class), $client->getContainer()->get(ShopContextResolver::class));
        static::ensureKernelShutdown();
    }

    public function testSupportPagesRequireAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/account/support');
        $this->assertResponseRedirects();
    }

    public function testCustomerCanCreateConversationAndPollMessages(): void
    {
        $client = static::createClient();
        $user = $this->createUser('customer');
        $client->loginUser($user);

        $client->request('POST', '/account/support', [
            'subject' => 'Need help with order',
            'message' => 'Hello support, I have a question.',
        ]);
        $this->assertResponseRedirects();

        /** @var SupportConversationRepository $repo */
        $repo = static::getContainer()->get(SupportConversationRepository::class);
        $conversations = $repo->findForCustomer($user, 10);
        $this->assertNotEmpty($conversations);
        $conversation = $conversations[0];

        $client->request('GET', '/support/api/conversations/' . $conversation->getId() . '/messages');
        $this->assertResponseIsSuccessful();
        $json = json_decode((string) $client->getResponse()->getContent(), true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('messages', $json);
        $this->assertNotEmpty($json['messages']);
    }

    public function testCustomerCannotAccessForeignConversation(): void
    {
        $client = static::createClient();
        $owner = $this->createUser('owner');
        $intruder = $this->createUser('intruder');

        $client->loginUser($owner);
        $client->request('POST', '/account/support', [
            'subject' => 'Owner conversation',
            'message' => 'Private thread',
        ]);
        $this->assertResponseRedirects();
        $client->request('GET', '/account/support');
        $this->assertResponseIsSuccessful();

        /** @var SupportConversationRepository $repo */
        $repo = static::getContainer()->get(SupportConversationRepository::class);
        $conversation = $repo->findForCustomer($owner, 1)[0];

        static::ensureKernelShutdown();
        $client = static::createClient();
        $client->loginUser($intruder);
        $client->request('GET', '/support/api/conversations/' . $conversation->getId() . '/messages');
        $this->assertSame(403, $client->getResponse()->getStatusCode());
    }

    public function testAdminCanOpenSupportInbox(): void
    {
        $client = static::createClient();
        $admin = $this->createUser('admin', ['ROLE_ADMIN']);
        $client->loginUser($admin);

        $client->request('GET', '/admin/support');
        $this->assertResponseIsSuccessful();
    }

    private function createUser(string $prefix, array $roles = ['ROLE_USER']): User
    {
        $user = new User();
        $user->setEmail($prefix . '_' . uniqid('', true) . '@example.com');
        $user->setPassword('password');
        $user->setRoles($roles);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }

    private function ensureSelfcodedSupportProvider(ThemeConfigService $themeConfigService, ShopContextResolver $shopContextResolver): void
    {
        $shop = $shopContextResolver->resolve();
        $theme = $themeConfigService->getOrCreateDraftTheme($shop);
        $config = $theme->getConfig();
        $support = is_array($config['support'] ?? null) ? $config['support'] : [];
        $support['provider'] = 'selfcoded';
        $config['support'] = $support;
        $themeConfigService->saveDraft($theme, $config);
        $themeConfigService->publish($theme);
    }
}

