<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/orders', name: 'admin_orders_')]
class OrderController extends AbstractController
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        $status = $request->query->get('status');
        $search = $request->query->get('search');
        
        $criteria = [];
        if ($status) {
            $criteria['status'] = $status;
        }
        
        $orders = $this->orderRepository->findBy(
            $criteria,
            ['createdAt' => 'DESC'],
            $limit,
            $offset
        );
        
        // Apply search filter if provided
        if ($search) {
            $orders = array_filter($orders, function(Order $order) use ($search) {
                return stripos($order->getOrderNumber(), $search) !== false 
                    || stripos($order->getEmail(), $search) !== false;
            });
        }
        
        $total = $this->orderRepository->count($criteria);
        
        return $this->render('admin/order/index.html.twig', [
            'orders' => $orders,
            'currentPage' => $page,
            'totalPages' => ceil($total / $limit),
            'status' => $status,
            'search' => $search,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $order = $this->orderRepository->find($id);
        
        if (!$order) {
            throw $this->createNotFoundException('Order not found');
        }
        
        return $this->render('admin/order/show.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route('/{id}/update-status', name: 'update_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function updateStatus(int $id, Request $request): Response
    {
        $order = $this->orderRepository->find($id);
        
        if (!$order) {
            throw $this->createNotFoundException('Order not found');
        }
        
        if ($this->isCsrfTokenValid('update_status_' . $order->getId(), $request->request->get('_token'))) {
            $newStatus = $request->request->get('status');
            $order->setStatus($newStatus);
            
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Order status updated successfully.');
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }
        
        return $this->redirectToRoute('admin_orders_show', ['id' => $order->getId()]);
    }
}

