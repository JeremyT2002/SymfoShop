<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Order;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class OrderStatusTest extends WebTestCase
{
    public function testGuestCanLookUpOrderWithNumberAndEmail(): void
    {
        $client = static::createClient();
        $order = $this->createOrder($client, 'guest-' . uniqid() . '@example.com');

        // Without the lookup the order is not visible
        $client->request('GET', '/order-status/' . $order->getOrderNumber());
        self::assertResponseRedirects('/order-status?number=' . $order->getOrderNumber());

        // Wrong e-mail: same generic error
        $this->lookUp($client, $order->getOrderNumber(), 'someone-else@example.com');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.alert-error');

        $this->lookUp($client, strtolower($order->getOrderNumber()), strtoupper($order->getEmail()));
        self::assertResponseRedirects('/order-status/' . $order->getOrderNumber());
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', $order->getOrderNumber());
        self::assertSelectorTextContains('main', 'DHL');
        self::assertSelectorExists('a[href*="dhl.de"]');
    }

    public function testAdminMarkingOrderShippedEmailsTheCustomer(): void
    {
        $client = static::createClient();
        $order = $this->createOrder($client, 'ship-' . uniqid() . '@example.com', 'processing');

        $admin = (new User())->setEmail('admin-ship-' . uniqid() . '@example.com')->setPassword('x')->setRoles(['ROLE_ADMIN']);
        $em = $client->getContainer()->get('doctrine')->getManager();
        $em->persist($admin);
        $em->flush();
        $client->loginUser($admin);

        $crawler = $client->request('GET', '/admin/orders/' . $order->getId());
        $form = $crawler->filter('form[action$="/update-status"]')->form();
        $form['order_status[status]'] = 'shipped';
        $form['order_status[carrier]'] = 'DPD';
        $form['order_status[trackingNumber]'] = '0123456789';
        $client->submit($form);

        self::assertEmailCount(1);
        $mail = self::getMailerMessage();
        self::assertEmailAddressContains($mail, 'to', $order->getEmail());
        self::assertEmailHtmlBodyContains($mail, 'tracking.dpd.de/status/de_DE/parcel/0123456789');
    }

    private function lookUp(KernelBrowser $client, string $number, string $email): void
    {
        $crawler = $client->request('GET', '/order-status');
        $client->submit($crawler->filter('form')->last()->form(['number' => $number, 'email' => $email]));
    }

    private function createOrder(KernelBrowser $client, string $email, string $status = 'shipped'): Order
    {
        $em = $client->getContainer()->get('doctrine')->getManager();
        \assert($em instanceof EntityManagerInterface);

        $order = (new Order())
            ->setOrderNumber('ORD-STATUS-' . strtoupper(uniqid()))
            ->setEmail($email)
            ->setCurrency('EUR')
            ->setStatus($status)
            ->setSubtotal(2500)
            ->setTaxTotal(399)
            ->setGrandTotal(2500)
            ->setCarrier($status === 'shipped' ? 'DHL' : null)
            ->setTrackingNumber($status === 'shipped' ? '00340434' : null)
            ->setFirstName('Erika')
            ->setLastName('Muster')
            ->setShippingStreet('Musterweg 1')
            ->setShippingPostalCode('12345')
            ->setShippingCity('Musterstadt')
            ->setShippingCountry('DE');
        $em->persist($order);
        $em->flush();

        return $order;
    }
}
