<?php

namespace App\Controller\Admin;

use App\Dashboard\AdminDashboardConfigService;
use App\Dashboard\Widget\WidgetRegistry;
use App\Dashboard\Widget\WidgetRenderer;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Render\RenderOpenApi;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class DashboardController extends AbstractController
{
    /** @var array<string, array<string, mixed>> */
    private array $widgetDataRuntimeCache = [];

    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly OrderRepository $orderRepository,
        private readonly UserRepository $userRepository,
        private readonly AdminDashboardConfigService $dashboardConfigService,
        private readonly WidgetRenderer $widgetRenderer,
        private readonly WidgetRegistry $widgetRegistry,
        private readonly RenderOpenApi $renderOpenApi,
        private readonly EntityManagerInterface $entityManager,
        private readonly CacheInterface $cache,
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
        $runtimeCacheKey = $type . ':' . md5((string) json_encode($settings));
        if (isset($this->widgetDataRuntimeCache[$runtimeCacheKey])) {
            return $this->widgetDataRuntimeCache[$runtimeCacheKey];
        }

        return match ($type) {
            'kpi_products' => $this->widgetDataRuntimeCache[$runtimeCacheKey] = ['count' => $this->getKpiCounts()['products']],
            'kpi_orders' => $this->widgetDataRuntimeCache[$runtimeCacheKey] = ['count' => $this->getKpiCounts()['orders']],
            'kpi_users' => $this->widgetDataRuntimeCache[$runtimeCacheKey] = ['count' => $this->getKpiCounts()['users']],
            'recent_orders' => [
                'orders' => $this->orderRepository->findBy(
                    [],
                    ['createdAt' => 'DESC'],
                    (int) ($settings['limit'] ?? 5)
                ),
            ],
            'chart_sales' => $this->widgetDataRuntimeCache[$runtimeCacheKey] = $this->cache->get(
                'admin_dashboard_widget_chart_sales_' . (int) ($settings['days'] ?? 30),
                function (ItemInterface $item) use ($settings): array {
                    $item->expiresAfter(60);

                    return $this->formatSalesChartData(
                        $this->orderRepository->getSalesOverTime((int) ($settings['days'] ?? 30))
                    );
                }
            ),
            'chart_orders_by_status' => $this->widgetDataRuntimeCache[$runtimeCacheKey] = $this->cache->get(
                'admin_dashboard_widget_chart_orders_by_status',
                function (ItemInterface $item): array {
                    $item->expiresAfter(60);
                    return [
                        'data' => $this->orderRepository->getOrdersByStatus(),
                    ];
                }
            ),
            default => $this->widgetDataRuntimeCache[$runtimeCacheKey] = [],
        };
    }

    /** @param array{labels: string[], orderCounts: int[], revenue: int[]} $data */
    private function formatSalesChartData(array $data): array
    {
        $data['revenueFormatted'] = array_map(fn (int $r) => round($r / 100, 2), $data['revenue']);
        return $data;
    }

    /** @return array{products:int, orders:int, users:int} */
    private function getKpiCounts(): array
    {
        return $this->cache->get('admin_dashboard_kpi_counts', function (ItemInterface $item): array {
            $item->expiresAfter(30);

            $row = $this->entityManager->getConnection()->fetchAssociative(
                'SELECT
                    (SELECT COUNT(*) FROM product) AS products_count,
                    (SELECT COUNT(*) FROM "order") AS orders_count,
                    (SELECT COUNT(*) FROM "user") AS users_count'
            );

            return [
                'products' => (int) ($row['products_count'] ?? 0),
                'orders' => (int) ($row['orders_count'] ?? 0),
                'users' => (int) ($row['users_count'] ?? 0),
            ];
        });
    }

    #[Route('/admin/api-docs', name: 'admin_api_docs')]
    public function apiDocs(): Response
    {
        $openApiSpec = $this->renderOpenApi->render(RenderOpenApi::JSON, 'default');

        return $this->render('admin/api_docs.html.twig', [
            'openApiSpec' => $openApiSpec,
        ]);
    }
}
