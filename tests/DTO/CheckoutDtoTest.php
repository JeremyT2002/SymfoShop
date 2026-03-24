<?php

namespace App\Tests\DTO;

use App\DTO\Checkout\AddressDTO;
use App\DTO\Checkout\CustomerInfoDTO;
use App\Service\Payment\Provider\PaymentResolution;
use PHPUnit\Framework\TestCase;

class CheckoutDtoTest extends TestCase
{
    public function testCustomerInfoDtoStoresValues(): void
    {
        $dto = new CustomerInfoDTO(
            'john@example.com',
            'John',
            'Doe',
            '+49 123 456789'
        );

        $this->assertSame('john@example.com', $dto->email);
        $this->assertSame('John', $dto->firstName);
        $this->assertSame('Doe', $dto->lastName);
        $this->assertSame('+49 123 456789', $dto->phone);
    }

    public function testCustomerInfoDtoAllowsNullPhone(): void
    {
        $dto = new CustomerInfoDTO('john@example.com', 'John', 'Doe');

        $this->assertNull($dto->phone);
    }

    public function testAddressDtoStoresValues(): void
    {
        $dto = new AddressDTO(
            'Musterstrasse 12',
            'Musterstadt',
            '12345',
            'DE',
            'NRW'
        );

        $this->assertSame('Musterstrasse 12', $dto->street);
        $this->assertSame('Musterstadt', $dto->city);
        $this->assertSame('12345', $dto->postalCode);
        $this->assertSame('DE', $dto->country);
        $this->assertSame('NRW', $dto->state);
    }

    public function testPaymentResolutionConstantsAndPayload(): void
    {
        $resolution = new PaymentResolution('dev_123', PaymentResolution::STATUS_SUCCEEDED, 42);

        $this->assertSame('succeeded', PaymentResolution::STATUS_SUCCEEDED);
        $this->assertSame('failed', PaymentResolution::STATUS_FAILED);
        $this->assertSame('pending', PaymentResolution::STATUS_PENDING);
        $this->assertSame('dev_123', $resolution->referenceId);
        $this->assertSame('succeeded', $resolution->status);
        $this->assertSame(42, $resolution->orderId);
    }
}

