<?php

namespace App\Controller\Admin;

use App\Dashboard\AdminDashboardConfigService;
use App\Dashboard\Widget\WidgetRegistry;
use App\Dashboard\Widget\WidgetRenderer;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly OrderRepository $orderRepository,
        private readonly UserRepository $userRepository,
        private readonly AdminDashboardConfigService $dashboardConfigService,
        private readonly WidgetRenderer $widgetRenderer,
        private readonly WidgetRegistry $widgetRegistry,
    ) {
    }

    #[Route('/admin', name: 'admin')]
    public function index(): Response
    {
        $user = $this->getUser();
        $config = $this->dashboardConfigService->getEffectiveConfig($user);

        $widgets = [];
        foreach ($config['widgets'] ?? [] as $item) {
            $type = $item['type'] ?? '';
            $settings = $item['settings'] ?? [];
            if (!$this->widgetRegistry->has($type)) {
                continue;
            }
            $data = $this->getWidgetData($type, $settings);
            $html = $this->widgetRenderer->render($type, $data, $settings);
            $widgets[] = [
                'id' => $item['id'] ?? uniqid('w'),
                'type' => $type,
                'title' => $this->widgetRegistry->get($type)?->title ?? $type,
                'html' => $html,
                'w' => (int) ($item['w'] ?? 2),
                'h' => (int) ($item['h'] ?? 1),
                'x' => (int) ($item['x'] ?? 0),
                'y' => (int) ($item['y'] ?? 0),
            ];
        }

        return $this->render('admin/dashboard.html.twig', [
            'widgets' => $widgets,
            'dashboardConfig' => $config,
        ]);
    }

    /** @return array<string, mixed> */
    private function getWidgetData(string $type, array $settings): array
    {
        return match ($type) {
            'kpi_products' => ['count' => $this->productRepository->count([])],
            'kpi_orders' => ['count' => $this->orderRepository->count([])],
            'kpi_users' => ['count' => $this->userRepository->count([])],
            'recent_orders' => [
                'orders' => $this->orderRepository->findBy(
                    [],
                    ['createdAt' => 'DESC'],
                    (int) ($settings['limit'] ?? 5)
                ),
            ],
            'chart_sales' => $this->formatSalesChartData(
                $this->orderRepository->getSalesOverTime((int) ($settings['days'] ?? 30))
            ),
            'chart_orders_by_status' => [
                'data' => $this->orderRepository->getOrdersByStatus(),
            ],
            default => [],
        };
    }

    /** @param array{labels: string[], orderCounts: int[], revenue: int[]} $data */
    private function formatSalesChartData(array $data): array
    {
        $data['revenueFormatted'] = array_map(fn (int $r) => round($r / 100, 2), $data['revenue']);
        return $data;
    }

    #[Route('/admin/api-docs', name: 'admin_api_docs')]
    public function apiDocs(): Response
    {
        return $this->redirect('/api/v1/docs');
    }
}
