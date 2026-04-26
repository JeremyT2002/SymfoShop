<?php

namespace App\Twig;

use App\Service\Admin\AdminActivityService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AdminActivityExtension extends AbstractExtension
{
    public function __construct(
        private readonly AdminActivityService $adminActivityService,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('admin_notifications', [$this, 'getAdminNotifications']),
        ];
    }

    /**
     * @return array{unreadCount:int,items:list<array<string,mixed>>}
     */
    public function getAdminNotifications(): array
    {
        return $this->adminActivityService->getNotifications();
    }
}

