<?php

namespace App\Service\Payment;

use App\Service\Payment\Provider\PaymentProviderInterface;

class PaymentProviderRegistry
{
    /** @var array<string, PaymentProviderInterface> */
    private array $providers = [];

    public function __construct(
        /** @var iterable<PaymentProviderInterface> */
        private readonly iterable $providerIterable,
        private readonly string $defaultProviderName
    ) {
        foreach ($this->providerIterable as $provider) {
            $this->providers[$provider->getName()] = $provider;
        }
    }

    public function get(string $name): PaymentProviderInterface
    {
        if (!isset($this->providers[$name])) {
            throw new \InvalidArgumentException(sprintf('Unknown payment provider: "%s". Available: %s', $name, implode(', ', array_keys($this->providers))));
        }
        return $this->providers[$name];
    }

    public function getDefault(): PaymentProviderInterface
    {
        return $this->get($this->defaultProviderName);
    }

    /** @return array<string, PaymentProviderInterface> */
    public function all(): array
    {
        return $this->providers;
    }

    /** @return list<string> */
    public function getAvailableNames(): array
    {
        return array_keys($this->providers);
    }
}
