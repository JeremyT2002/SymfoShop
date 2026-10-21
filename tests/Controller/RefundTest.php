<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Payment;
use App\Entity\Product;
use App\Entity\ProductStatus;
use App\Entity\ProductVariant;
use App\Entity\Refund;
use App\Entity\ReturnRequest;
use App\Entity\StockItem;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RefundTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = $this->client->getContainer()->get('doctrine')->getManager();
    }

    public function testPartialThenFullRefundThroughProvider(): void
    {
        [$order] = $this->createPaidOrder(provider: 'dev');
        $returnRequest = (new ReturnRequest())->setOrderNumber($order->getOrderNumber())->setEmail($order->getEmail())->setReason('Too small');
        $this->em->persist($returnRequest);
        $this->em->flush();
        $this->loginAdmin();

        $this->submitRefund($order, ['amount' => '10,00', 'reason' => 'Damaged box', 'return_request' => (string) $returnRequest->getId()]);

        self::assertEmailCount(1);
        $mail = self::getMailerMessage();
        self::assertEmailAddressContains($mail, 'to', $order->getEmail());
        self::assertEmailAttachmentCount($mail, 1);

        $refunds = $this->refunds($order);
        self::assertCount(1, $refunds);
        self::assertSame(1000, $refunds[0]->getAmount());
        self::assertSame(160, $refunds[0]->getTaxAmount(), '19 % VAT share of 10.00 gross');
        self::assertSame('dev', $refunds[0]->getProvider());
        self::assertStringStartsWith('dev_re_', (string) $refunds[0]->getProviderReference());
        self::assertMatchesRegularExpression('/^CN-\d{4}-\d{4}$/', $refunds[0]->getCreditNoteNumber());
        self::assertSame('refunded', $this->em->find(ReturnRequest::class, $returnRequest->getId())->getStatus());
        self::assertSame('paid', $this->reload($order)->getStatus(), 'partial refund keeps the order status');

        // More than what is left: rejected, nothing recorded
        $this->submitRefund($order, ['amount' => '50.00']);
        self::assertCount(1, $this->refunds($order));

        $this->submitRefund($order, ['amount' => '15.00']);
        self::assertCount(2, $this->refunds($order));
        self::assertSame('refunded', $this->reload($order)->getStatus());

        // Nothing left to refund: no form any more
        $this->client->request('GET', '/admin/orders/' . $order->getId());
        self::assertSelectorNotExists('form[action$="/refund"]');
    }

    public function testManualRefundRestocksOnce(): void
    {
        [$order, $variant] = $this->createPaidOrder(provider: null);
        $this->loginAdmin();

        $this->submitRefund($order, ['amount' => '5.00', 'restock' => '1']);
        $this->submitRefund($order, ['amount' => '5.00', 'restock' => '1']);

        $refunds = $this->refunds($order);
        self::assertCount(2, $refunds);
        self::assertSame(Refund::PROVIDER_MANUAL, $refunds[0]->getProvider());
        self::assertTrue($refunds[0]->isRestocked());
        self::assertFalse($refunds[1]->isRestocked());

        $stock = $this->em->getRepository(StockItem::class)->findOneBy(['variant' => $variant->getId()]);
        self::assertSame(12, $stock->getOnHand(), '10 + 2 returned items, only once');
    }

    public function testCustomerDownloadsOwnCreditNoteOnly(): void
    {
        $customer = $this->createUser('ROLE_USER');
        [$order] = $this->createPaidOrder(provider: 'dev', user: $customer);
        $this->loginAdmin();
        $this->submitRefund($order, ['amount' => '1.00']);
        $number = $this->refunds($order)[0]->getCreditNoteNumber();

        $this->client->loginUser($customer);
        $this->client->request('GET', '/account?tab=invoices');
        self::assertSelectorTextContains('main', $number);
        $this->client->request('GET', '/account/credit-note/' . $number . '/download');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');

        $this->client->loginUser($this->createUser('ROLE_USER'));
        $this->client->request('GET', '/account/credit-note/' . $number . '/download');
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @param array<string, string> $fields
     */
    private function submitRefund(Order $order, array $fields): void
    {
        $crawler = $this->client->request('GET', '/admin/orders/' . $order->getId());
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[action$="/refund"]')->form();
        foreach ($fields as $name => $value) {
            if ($name === 'restock') {
                $form['restock']->tick();
                continue;
            }
            $form[$name] = $value;
        }
        $this->client->submit($form);
        self::assertResponseRedirects('/admin/orders/' . $order->getId());
    }

    /**
     * @return array{Order, ProductVariant}
     */
    private function createPaidOrder(?string $provider, ?User $user = null): array
    {
        $suffix = strtoupper(uniqid());
        $product = (new Product())->setName('Refund product')->setSlug('refund-' . strtolower($suffix))->setStatus(ProductStatus::ACTIVE)->setTaxClass('standard');
        $variant = (new ProductVariant())->setProduct($product)->setSku('RF-' . $suffix)->setPriceAmount(1250)->setCurrency('EUR');
        $product->addVariant($variant);
        $stock = (new StockItem())->setVariant($variant)->setOnHand(10)->setReserved(0);
        $variant->setStockItem($stock);

        $order = (new Order())
            ->setOrderNumber('ORD-RF-' . $suffix)
            ->setEmail($user?->getEmail() ?? 'refund-' . strtolower($suffix) . '@example.com')
            ->setUser($user)
            ->setCurrency('EUR')
            ->setStatus('paid')
            ->setSubtotal(2500)
            ->setTaxTotal(399)
            ->setGrandTotal(2500)
            ->setPricesIncludeTax(true)
            ->setShippingCountry('DE');
        $item = (new OrderItem())
            ->setOrder($order)
            ->setProductVariant($variant)
            ->setSku($variant->getSku())
            ->setNameSnapshot('Refund product')
            ->setQuantity(2)
            ->setUnitPriceAmount(1250)
            ->setTotalAmount(2500)
            ->setTaxRate('0.19');
        $order->addItem($item);

        foreach ([$product, $variant, $stock, $order, $item] as $entity) {
            $this->em->persist($entity);
        }

        if ($provider !== null) {
            $payment = (new Payment())->setOrder($order)->setProvider($provider)->setPaymentIntentId($provider . '_' . $suffix)
                ->setStatus('succeeded')->setAmount(2500)->setCurrency('EUR');
            $order->addPayment($payment);
            $this->em->persist($payment);
        }

        $this->em->flush();

        return [$order, $variant];
    }

    private function loginAdmin(): void
    {
        $this->client->loginUser($this->createUser('ROLE_ADMIN'));
    }

    private function createUser(string $role): User
    {
        $user = (new User())->setEmail('refund-user-' . uniqid() . '@example.com')->setPassword('x')->setRoles([$role]);
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    /**
     * @return list<Refund>
     */
    private function refunds(Order $order): array
    {
        $this->em->clear();

        return $this->em->getRepository(Refund::class)->findBy(['order' => $order->getId()], ['id' => 'ASC']);
    }

    private function reload(Order $order): Order
    {
        $this->em->clear();

        return $this->em->find(Order::class, $order->getId());
    }
}
