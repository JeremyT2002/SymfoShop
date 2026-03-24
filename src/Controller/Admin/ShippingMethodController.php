<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ShippingMethod;
use App\Repository\ShippingMethodRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/shipping-methods', name: 'admin_shipping_methods_')]
class ShippingMethodController extends AbstractController
{
    public function __construct(
        private readonly ShippingMethodRepository $shippingMethodRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/shipping_method/index.html.twig', [
            'methods' => $this->shippingMethodRepository->findAllOrdered(),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $method = new ShippingMethod();
        if ($request->isMethod('POST')) {
            $code = mb_strtolower(trim((string) $request->request->get('code', '')));
            $name = trim((string) $request->request->get('name', ''));
            $amountCents = (int) $request->request->get('amountCents', 0);
            $sortOrder = (int) $request->request->get('sortOrder', 0);
            $isActive = $request->request->getBoolean('isActive', true);

            if ($code === '' || $name === '') {
                $this->addFlash('error', 'admin.shipping_methods.flash.code_name_required');
            } else {
                $method->setCode($code)
                    ->setName($name)
                    ->setAmountCents($amountCents)
                    ->setSortOrder($sortOrder)
                    ->setIsActive($isActive);

                $this->entityManager->persist($method);
                $this->entityManager->flush();
                $this->addFlash('success', 'admin.shipping_methods.flash.created');

                return $this->redirectToRoute('admin_shipping_methods_index');
            }
        }

        return $this->render('admin/shipping_method/new.html.twig', [
            'method' => $method,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(ShippingMethod $method, Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $code = mb_strtolower(trim((string) $request->request->get('code', '')));
            $name = trim((string) $request->request->get('name', ''));
            $amountCents = (int) $request->request->get('amountCents', 0);
            $sortOrder = (int) $request->request->get('sortOrder', 0);
            $isActive = $request->request->getBoolean('isActive', false);

            if ($code === '' || $name === '') {
                $this->addFlash('error', 'admin.shipping_methods.flash.code_name_required');
            } else {
                $method->setCode($code)
                    ->setName($name)
                    ->setAmountCents($amountCents)
                    ->setSortOrder($sortOrder)
                    ->setIsActive($isActive);

                $this->entityManager->flush();
                $this->addFlash('success', 'admin.shipping_methods.flash.updated');

                return $this->redirectToRoute('admin_shipping_methods_index');
            }
        }

        return $this->render('admin/shipping_method/edit.html.twig', [
            'method' => $method,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(ShippingMethod $method, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete_shipping_method_' . $method->getId(), (string) $request->request->get('_token'))) {
            $this->entityManager->remove($method);
            $this->entityManager->flush();
            $this->addFlash('success', 'admin.shipping_methods.flash.removed');
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }

        return $this->redirectToRoute('admin_shipping_methods_index');
    }
}
