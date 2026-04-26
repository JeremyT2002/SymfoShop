<?php

namespace App\Controller\Admin;

use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/search', name: 'admin_search', methods: ['GET'])]
class SearchController extends AbstractController
{
    private const SEARCH_TYPES = ['all', 'products', 'orders', 'users'];

    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly OrderRepository $orderRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $query = trim((string) $request->query->get('q', ''));
        $type = (string) $request->query->get('type', 'all');
        if (!in_array($type, self::SEARCH_TYPES, true)) {
            $type = 'all';
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $products = [];
        $orders = [];
        $users = [];
        $productsCount = 0;
        $ordersCount = 0;
        $usersCount = 0;
        $totalForType = 0;

        if ($query !== '') {
            $productsCount = $this->productRepository->countForAdminList(null, $query);
            $ordersCount = $this->orderRepository->countForAdminList(null, $query);
            $usersCount = $this->userRepository->countForAdminList($query, null);

            if ($type === 'all') {
                $products = $this->productRepository->findForAdminList(null, $query, 8, 0, 'createdAt', 'DESC');
                $orders = $this->orderRepository->findForAdminList(null, $query, 8, 0, 'createdAt', 'DESC');
                $users = $this->userRepository->findForAdminList($query, null, 8, 0, 'createdAt', 'DESC');
            } elseif ($type === 'products') {
                $totalForType = $productsCount;
                $products = $this->productRepository->findForAdminList(null, $query, $perPage, $offset, 'createdAt', 'DESC');
            } elseif ($type === 'orders') {
                $totalForType = $ordersCount;
                $orders = $this->orderRepository->findForAdminList(null, $query, $perPage, $offset, 'createdAt', 'DESC');
            } else {
                $totalForType = $usersCount;
                $users = $this->userRepository->findForAdminList($query, null, $perPage, $offset, 'createdAt', 'DESC');
            }
        }

        return $this->render('admin/search/index.html.twig', [
            'query' => $query,
            'type' => $type,
            'page' => $page,
            'perPage' => $perPage,
            'totalForType' => $totalForType,
            'totalPages' => $type === 'all' ? 1 : max(1, (int) ceil($totalForType / $perPage)),
            'products' => $products,
            'orders' => $orders,
            'users' => $users,
            'productsCount' => $productsCount,
            'ordersCount' => $ordersCount,
            'usersCount' => $usersCount,
        ]);
    }
}

