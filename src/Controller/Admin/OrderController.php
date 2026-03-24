<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Repository\InvoiceRepository;
use App\Repository\OrderRepository;
use App\Service\Invoice\InvoiceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/orders', name: 'admin_orders_')]
class OrderController extends AbstractController
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly InvoiceService $invoiceService,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
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

    #[Route('/regenerate-all-invoices', name: 'regenerate_all_invoices', methods: ['POST'])]
    public function regenerateAllInvoices(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('regenerate_all_invoices', (string) $request->request->get('_token'))) {
            $this->addFlash('error', $this->translator->trans('admin.orders.flash.invalid_csrf'));

            return $this->redirectToRoute('admin_orders_index');
        }

        $result = $this->invoiceService->regenerateAllInvoicePdfs();

        if ($result['success'] === 0 && $result['failed'] === 0) {
            $this->addFlash('info', $this->translator->trans('admin.orders.flash.regenerate_none'));
        } elseif ($result['failed'] === 0) {
            $this->addFlash(
                'success',
                $this->translator->trans('admin.orders.flash.regenerate_success', ['%count%' => (string) $result['success']])
            );
        } else {
            $this->addFlash(
                'warning',
                $this->translator->trans('admin.orders.flash.regenerate_partial', [
                    '%success%' => (string) $result['success'],
                    '%failed%' => (string) $result['failed'],
                ])
            );
            if ($result['errors'] !== []) {
                $this->addFlash('error', implode(' | ', array_slice($result['errors'], 0, 5))
                    . (count($result['errors']) > 5 ? ' …' : ''));
            }
        }

        return $this->redirectToRoute('admin_orders_index');
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $order = $this->orderRepository->find($id);
        
        if (!$order) {
            throw $this->createNotFoundException('Order not found');
        }

        $invoice = $this->invoiceRepository->findOneByOrderId((int) $order->getId());

        return $this->render('admin/order/show.html.twig', [
            'order' => $order,
            'invoice' => $invoice,
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
            
            $this->addFlash('success', $this->translator->trans('admin.orders.flash.status_updated'));
        } else {
            $this->addFlash('error', $this->translator->trans('admin.orders.flash.invalid_csrf'));
        }
        
        return $this->redirectToRoute('admin_orders_show', ['id' => $order->getId()]);
    }
}

