<?php

declare(strict_types=1);

namespace App\Tests\Controller\Security;

use App\Entity\Order;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class EmailVerificationTest extends WebTestCase
{
    public function testVerificationLinkVerifiesAndAssignsGuestOrders(): void
    {
        $client = static::createClient();
        $em = $this->em($client);

        $email = 'verify-' . uniqid('', true) . '@example.com';
        $user = (new User())->setEmail($email)->setPassword('password');
        $em->persist($user);
        $guestOrder = $this->guestOrder($email);
        $em->persist($guestOrder);
        $em->flush();

        $client->loginUser($user);
        $crawler = $client->request('GET', '/account');
        self::assertSelectorExists('form[action="/verify-email/resend"]');

        $client->submit($crawler->filter('form[action="/verify-email/resend"]')->form());
        self::assertEmailCount(1);
        $html = (string) self::getMailerMessage()?->getHtmlBody();
        self::assertSame(1, preg_match('#https?://[^"]+/verify-email/\d+\?[^"]+#', html_entity_decode($html), $m));

        $client->request('GET', $m[0]);
        self::assertResponseRedirects('/account');

        $em->clear();
        self::assertTrue($em->getRepository(User::class)->find($user->getId())->isVerified());
        self::assertSame($user->getId(), $em->getRepository(Order::class)->find($guestOrder->getId())->getUser()?->getId());
    }

    public function testTamperedLinkIsRejected(): void
    {
        $client = static::createClient();
        $em = $this->em($client);
        $user = (new User())->setEmail('verify-bad-' . uniqid('', true) . '@example.com')->setPassword('password');
        $em->persist($user);
        $em->flush();

        $client->request('GET', '/verify-email/' . $user->getId() . '?fp=abc&_hash=forged');
        self::assertResponseRedirects('/login');

        $em->clear();
        self::assertFalse($em->getRepository(User::class)->find($user->getId())->isVerified());
    }

    private function em(KernelBrowser $client): EntityManagerInterface
    {
        $em = $client->getContainer()->get('doctrine')->getManager();
        \assert($em instanceof EntityManagerInterface);

        return $em;
    }

    private function guestOrder(string $email): Order
    {
        return (new Order())
            ->setOrderNumber('ORD-VERIFY-' . uniqid())
            ->setEmail(strtoupper($email))
            ->setCurrency('EUR')
            ->setStatus('paid')
            ->setSubtotal(1000)
            ->setTaxTotal(160)
            ->setGrandTotal(1000);
    }
}
