<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\NewsletterSubscriberRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class NewsletterTest extends WebTestCase
{
    public function testDoubleOptInAndUnsubscribe(): void
    {
        $client = static::createClient();
        $email = 'news-' . uniqid() . '@example.com';

        $this->subscribe($client, $email, consent: true);
        self::assertResponseRedirects();
        self::assertEmailCount(1);

        $subscriber = $this->repository()->findOneByEmail($email);
        self::assertNotNull($subscriber);
        self::assertFalse($subscriber->isActive(), 'not active before confirmation');

        $html = html_entity_decode((string) self::getMailerMessage()?->getHtmlBody());
        self::assertSame(1, preg_match('#https?://[^"]+/newsletter/confirm/[a-f0-9]{48}#', $html, $m));
        $client->request('GET', $m[0]);
        self::assertResponseRedirects('/');
        self::assertTrue($this->repository()->findOneByEmail($email)->isActive());

        $client->request('GET', '/newsletter/unsubscribe/' . $subscriber->getToken());
        self::assertFalse($this->repository()->findOneByEmail($email)->isActive());
    }

    public function testConsentIsRequired(): void
    {
        $client = static::createClient();
        $email = 'news-noconsent-' . uniqid() . '@example.com';

        $this->subscribe($client, $email, consent: false);

        self::assertEmailCount(0);
        self::assertNull($this->repository()->findOneByEmail($email));
    }

    public function testAdminListAndExport(): void
    {
        $client = static::createClient();
        $admin = (new User())->setEmail('news-admin-' . uniqid() . '@example.com')->setPassword('x')->setRoles(['ROLE_ADMIN']);
        $em = $client->getContainer()->get('doctrine')->getManager();
        $em->persist($admin);
        $em->flush();
        $client->loginUser($admin);

        $client->request('GET', '/admin/newsletter');
        self::assertResponseIsSuccessful();

        $client->request('GET', '/admin/newsletter/export.csv');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/csv; charset=utf-8');
    }

    private function subscribe(KernelBrowser $client, string $email, bool $consent): void
    {
        $crawler = $client->request('GET', '/');
        $form = $crawler->filter('form[action="/newsletter/subscribe"]')->first()->form();
        $form['email'] = $email;
        if (!$consent) {
            $form['consent']->untick();
        } else {
            $form['consent']->tick();
        }
        $client->submit($form);
    }

    private function repository(): NewsletterSubscriberRepository
    {
        $repository = static::getContainer()->get(NewsletterSubscriberRepository::class);
        static::getContainer()->get('doctrine')->getManager()->clear();

        return $repository;
    }
}
