<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\ReturnRequestRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/return-requests', name: 'admin_return_requests_')]
final class ReturnRequestAdminController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(ReturnRequestRepository $returnRequestRepository): Response
    {
        return $this->render('admin/return_request/index.html.twig', [
            'requests' => $returnRequestRepository->findRecent(200),
        ]);
    }
}
