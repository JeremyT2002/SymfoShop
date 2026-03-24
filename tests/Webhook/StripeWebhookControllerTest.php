<?php

declare(strict_types=1);

namespace App\Tests\Webhook;

use App\Entity\Order;
use App\Entity\Payment;
use App\Entity\ProcessedWebhookEvent;
use App\Repository\ProcessedWebhookEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class StripeWebhookControllerTest extends WebTestCase
{
    private const WEBHOOK_SECRET = 'whsec_phpunit_symfoshop_test_signing_secret';

    public function testRejectsMissingSignature(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/webhook/stripe',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{"id":"evt_x"}'
        );

        $this->assertSame(400, $client->getResponse()->getStatusCode());
    }

    public function testRejectsInvalidSignature(): void
    {
        $client = static::createClient();
        $payload = '{"id":"evt_bad_sig","object":"event","type":"payment_intent.succeeded","data":{"object":{"id":"pi_x","object":"payment_intent"}}}';
        $client->request(
            'POST',
            '/webhook/stripe',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => 't=' . time() . ',v1=deadbeef',
            ],
            $payload
        );

        $this->assertSame(400, $client->getResponse()->getStatusCode());
    }

    public function testDuplicateCompletedEventIsIdempotent(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $entityManager = $container->get('doctrine')->getManager();
        $processedWebhookRepository = $container->get(ProcessedWebhookEventRepository::class);

        $intentId = 'pi_phpunit_' . bin2hex(random_bytes(6));
        $eventId = 'evt_phpunit_' . bin2hex(random_bytes(6));
        $order = $this->createOrderPaymentPending($entityManager);
        $this->createStripePayment($entityManager, $order, $intentId);

        $payload = $this->buildPaymentIntentSucceededPayload($eventId, $intentId);
        $sig = StripeWebhookSignatureHelper::header($payload, self::WEBHOOK_SECRET);

        $client->request(
            'POST',
            '/webhook/stripe',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $sig],
            $payload
        );
        $this->assertSame(200, $client->getResponse()->getStatusCode());

        $entityManager->clear();
        $stored = $processedWebhookRepository->findOneByEventId($eventId);
        $this->assertNotNull($stored);
        $this->assertSame(ProcessedWebhookEvent::STATUS_COMPLETED, $stored->getStatus());

        $client->request(
            'POST',
            '/webhook/stripe',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $sig],
            $payload
        );
        $this->assertSame(200, $client->getResponse()->getStatusCode());

        $entityManager->clear();
        $rows = $processedWebhookRepository->createQueryBuilder('p')
            ->where('p.eventId = :id')
            ->setParameter('id', $eventId)
            ->getQuery()
            ->getResult();
        $this->assertCount(1, $rows);
    }

    private function createOrderPaymentPending(EntityManagerInterface $entityManager): Order
    {
        $order = new Order();
        $order->setOrderNumber('ORD-WH-' . uniqid());
        $order->setEmail('wh@example.com');
        $order->setCurrency('EUR');
        $order->setStatus('payment_pending');
        $order->setSubtotal(4000);
        $order->setTaxTotal(800);
        $order->setGrandTotal(4800);
        $entityManager->persist($order);
        $entityManager->flush();

        return $order;
    }

    private function createStripePayment(EntityManagerInterface $entityManager, Order $order, string $intentId): Payment
    {
        $payment = new Payment();
        $payment->setOrder($order);
        $payment->setProvider('stripe');
        $payment->setPaymentIntentId($intentId);
        $payment->setStatus('pending');
        $payment->setAmount($order->getGrandTotal());
        $payment->setCurrency($order->getCurrency());
        $entityManager->persist($payment);
        $entityManager->flush();

        return $payment;
    }

    private function buildPaymentIntentSucceededPayload(string $eventId, string $paymentIntentId): string
    {
        $data = [
            'id' => $eventId,
            'object' => 'event',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => $paymentIntentId,
                    'object' => 'payment_intent',
                    'amount' => 4800,
                    'currency' => 'eur',
                ],
            ],
        ];

        return json_encode($data, JSON_THROW_ON_ERROR);
    }
}
