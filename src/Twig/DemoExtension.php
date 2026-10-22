<?php

declare(strict_types=1);

namespace App\Twig;

use App\Demo\DemoAccounts;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class DemoExtension extends AbstractExtension
{
    public function __construct(
        private readonly DemoAccounts $demoAccounts
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('demo_accounts', [$this, 'getDemoAccounts']),
        ];
    }

    /**
     * Demo logins for the sign-in page; empty unless DEMO_MODE=1.
     *
     * @return list<string>
     */
    public function getDemoAccounts(): array
    {
        return array_keys($this->demoAccounts->all());
    }
}
