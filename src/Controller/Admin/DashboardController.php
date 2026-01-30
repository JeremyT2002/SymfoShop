<?php

namespace App\Controller\Admin;

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
        private readonly UserRepository $userRepository
    ) {
    }

    #[Route('/admin', name: 'admin')]
    public function index(): Response
    {
        // Get statistics for dashboard
        $totalProducts = $this->productRepository->count([]);
        $totalOrders = $this->orderRepository->count([]);
        $totalUsers = $this->userRepository->count([]);
        
        // Get recent orders
        $recentOrders = $this->orderRepository->findBy(
            [],
            ['createdAt' => 'DESC'],
            5
        );
        
        return $this->render('admin/dashboard.html.twig', [
            'totalProducts' => $totalProducts,
            'totalOrders' => $totalOrders,
            'totalUsers' => $totalUsers,
            'recentOrders' => $recentOrders,
        ]);
    }

    #[Route('/admin/api-docs', name: 'admin_api_docs')]
    public function apiDocs(): Response
    {
        // Redirect to Swagger UI documentation
        return $this->redirect('/api/v1/docs');
    }
}

