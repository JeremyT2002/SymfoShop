<?php

declare(strict_types=1);

namespace App\Tests\Config;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final class SecurityHardeningTest extends KernelTestCase
{
    public function testRegistrationRateLimiterIsWired(): void
    {
        self::bootKernel(['environment' => 'test']);

        $factory = self::getContainer()->get('limiter.registration_limiter');
        $this->assertInstanceOf(RateLimiterFactory::class, $factory);
    }
}
