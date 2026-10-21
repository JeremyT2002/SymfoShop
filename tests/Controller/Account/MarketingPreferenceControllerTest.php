<?php

namespace App\Tests\Controller\Account;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\UriSigner;

class MarketingPreferenceControllerTest extends WebTestCase
{
    public function testSignedOptOutLinkDisablesMarketingOptIn(): void
    {
        $client = static::createClient();
        $user = $this->createOptedInUser($client);

        $signer = $client->getContainer()->get(UriSigner::class);
        $client->request('GET', $signer->sign('http://localhost/account/marketing/opt-out/' . $user->getId()));
        self::assertResponseRedirects('/');

        self::assertFalse($this->reload($client, $user)->isMarketingOptIn());
    }

    public function testUnsignedOptOutLinkIsRejected(): void
    {
        $client = static::createClient();
        $user = $this->createOptedInUser($client);

        $client->request('GET', '/account/marketing/opt-out/' . $user->getId());
        self::assertResponseRedirects('/');

        self::assertTrue($this->reload($client, $user)->isMarketingOptIn());
    }

    private function createOptedInUser(KernelBrowser $client): User
    {
        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        \assert($entityManager instanceof EntityManagerInterface);

        $user = new User();
        $user->setEmail('optout-' . uniqid('', false) . '@example.com');
        $user->setPassword('password');
        $user->setMarketingOptIn(true);
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function reload(KernelBrowser $client, User $user): User
    {
        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $entityManager->clear();
        $reloaded = $entityManager->getRepository(User::class)->find($user->getId());
        self::assertNotNull($reloaded);

        return $reloaded;
    }
}
