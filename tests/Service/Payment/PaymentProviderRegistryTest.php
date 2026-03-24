<?php

namespace App\Tests\Service\Payment;

use App\Entity\Order;
use App\Service\Payment\PaymentProviderRegistry;
use App\Service\Payment\Provider\DevPaymentProvider;
use App\Service\Payment\Provider\PaymentProviderInterface;
use App\Service\Payment\Provider\PaymentResolution;
use App\Service\Payment\Provider\PaymentResult;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class PaymentProviderRegistryTest extends TestCase
{
    public function testGetDefaultAndAll(): void
    {
        $dev = new DevPaymentProvider();
        $registry = new PaymentProviderRegistry([$dev], DevPaymentProvider::NAME);

        $this->assertSame($dev, $registry->getDefault());
        $this->assertSame(['dev' => $dev], $registry->all());
        $this->assertSame(['dev'], $registry->getAvailableNames());
    }

    public function testGetByName(): void
    {
        $a = new class implements PaymentProviderInterface {
            public function getName(): string
            {
                return 'a';
            }

            public function startPayment(Order $order): PaymentResult
            {
                return new PaymentResult('a', 'ref');
            }

            public function handleReturn(Request $request): ?PaymentResolution
            {
                return null;
            }

            public function handleWebhook(Request $request): ?PaymentResolution
            {
                return null;
            }

            public function getClientSecretForReference(string $referenceId): ?string
            {
                return null;
            }
        };

        $registry = new PaymentProviderRegistry([$a], 'a');
        $this->assertSame($a, $registry->get('a'));
    }

    public function testGetUnknownProviderThrows(): void
    {
        $registry = new PaymentProviderRegistry([new DevPaymentProvider()], DevPaymentProvider::NAME);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown payment provider');

        $registry->get('missing');
    }
}
