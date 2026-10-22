<?php

namespace App\Controller\Admin;

use App\Service\System\AppUpdateService;
use App\Service\Admin\DashboardMetricsService;
use Nelmio\ApiDocBundle\Render\RenderOpenApi;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    public function __construct(
        private readonly DashboardMetricsService $dashboardMetricsService,
        private readonly RenderOpenApi $renderOpenApi,
        private readonly AppUpdateService $appUpdateService,
    ) {
    }

    #[Route('/admin', name: 'admin')]
    public function index(Request $request): Response
    {
        // Support and warehouse staff have no access to shop figures: start with the orders
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin_orders_index');
        }

        $period = $request->query->getString('period', '7d');
        $dashboard = $this->dashboardMetricsService->getDashboardData($period);

        return $this->render('admin/dashboard.html.twig', [
            'dashboard' => $dashboard,
            'period' => $dashboard['period'],
            'app_update' => $this->appUpdateService->checkForUpdates(),
        ]);
    }

    #[Route('/admin/api-docs', name: 'admin_api_docs')]
    public function apiDocs(): Response
    {
        $openApiSpec = $this->renderOpenApi->render(RenderOpenApi::JSON, 'default');
        // Embedded in a <script> tag: escape "<" and ">" so the spec cannot close it.
        $openApiSpec = json_encode(json_decode($openApiSpec), \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_UNESCAPED_SLASHES);

        return $this->render('admin/api_docs.html.twig', [
            'openApiSpec' => $openApiSpec,
        ]);
    }
}
