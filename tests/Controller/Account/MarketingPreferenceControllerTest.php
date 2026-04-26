<?php

namespace App\Tests\Controller\Account;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class MarketingPreferenceControllerTest extends WebTestCase
{
    public function testOptOutRouteDisablesMarketingOptIn(): void
    {
        $client = static::createClient();
        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        \assert($entityManager instanceof EntityManagerInterface);

        $user = new User();
        $user->setEmail('optout-' . uniqid('', false) . '@example.com');
        $user->setPassword('password');
        $user->setMarketingOptIn(true);
        $entityManager->persist($user);
        $entityManager->flush();

        $client->request('GET', '/account/marketing/opt-out/' . $user->getId());
        self::assertResponseRedirects('/');

        $entityManager->clear();
        $reloaded = $entityManager->getRepository(User::class)->find($user->getId());
        self::assertNotNull($reloaded);
        self::assertFalse($reloaded->isMarketingOptIn());
    }
}

