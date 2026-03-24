<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ReturnRequest;
use App\Form\ReturnRequestFormType;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ReturnRequestController extends AbstractController
{
    #[Route('/support/return-request', name: 'return_request', methods: ['GET', 'POST'])]
    public function __invoke(
        Request $request,
        EntityManagerInterface $entityManager,
        OrderRepository $orderRepository,
    ): Response {
        $returnRequest = new ReturnRequest();
        $form = $this->createForm(ReturnRequestFormType::class, $returnRequest);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $order = $orderRepository->findOneByOrderNumberAndEmail(
                $returnRequest->getOrderNumber(),
                $returnRequest->getEmail()
            );

            if ($order === null) {
                $this->addFlash('error', 'return_request.flash.order_not_found');

                return $this->redirectToRoute('return_request');
            }

            $entityManager->persist($returnRequest);
            $entityManager->flush();
            $this->addFlash('success', 'return_request.flash.submitted');

            return $this->redirectToRoute('return_request');
        }

        return $this->render('return_request/index.html.twig', [
            'form' => $form,
        ]);
    }
}
