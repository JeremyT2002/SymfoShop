<?php

namespace App\Controller\Admin;

use App\Entity\PaymentMethod;
use App\Repository\PaymentMethodRepository;
use App\Service\Payment\PaymentProviderRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/payment-methods', name: 'admin_payment_methods_')]
class PaymentMethodController extends AbstractController
{
    public function __construct(
        private readonly PaymentMethodRepository $paymentMethodRepository,
        private readonly PaymentProviderRegistry $paymentProviderRegistry,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/payment_method/index.html.twig', [
            'methods' => $this->paymentMethodRepository->findAllOrdered(),
            'availableProviderNames' => $this->paymentProviderRegistry->getAvailableNames(),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $method = new PaymentMethod();
        if ($request->isMethod('POST')) {
            $code = mb_strtolower(trim((string) $request->request->get('code', '')));
            $name = trim((string) $request->request->get('name', ''));
            $sortOrder = (int) $request->request->get('sortOrder', 0);
            $isActive = $request->request->getBoolean('isActive', true);
            $isDefault = $request->request->getBoolean('isDefault', false);

            if ($code === '' || $name === '') {
                $this->addFlash('error', 'admin.payment_methods.flash.code_name_required');
            } elseif (!in_array($code, $this->paymentProviderRegistry->getAvailableNames(), true)) {
                $this->addFlash('error', 'admin.payment_methods.flash.provider_unavailable');
            } else {
                $method->setCode($code)
                    ->setName($name)
                    ->setSortOrder($sortOrder)
                    ->setIsActive($isActive)
                    ->setIsDefault($isDefault);

                if ($isDefault) {
                    $this->paymentMethodRepository->clearDefaultFlags();
                }

                $this->entityManager->persist($method);
                $this->entityManager->flush();
                $this->addFlash('success', 'admin.payment_methods.flash.created');

                return $this->redirectToRoute('admin_payment_methods_index');
            }
        }

        return $this->render('admin/payment_method/new.html.twig', [
            'availableProviderNames' => $this->paymentProviderRegistry->getAvailableNames(),
            'method' => $method,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(PaymentMethod $method, Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $code = mb_strtolower(trim((string) $request->request->get('code', '')));
            $name = trim((string) $request->request->get('name', ''));
            $sortOrder = (int) $request->request->get('sortOrder', 0);
            $isActive = $request->request->getBoolean('isActive', false);
            $isDefault = $request->request->getBoolean('isDefault', false);

            if ($code === '' || $name === '') {
                $this->addFlash('error', 'admin.payment_methods.flash.code_name_required');
            } elseif (!in_array($code, $this->paymentProviderRegistry->getAvailableNames(), true)) {
                $this->addFlash('error', 'admin.payment_methods.flash.provider_unavailable');
            } else {
                if ($isDefault) {
                    $this->paymentMethodRepository->clearDefaultFlags();
                }

                $method->setCode($code)
                    ->setName($name)
                    ->setSortOrder($sortOrder)
                    ->setIsActive($isActive)
                    ->setIsDefault($isDefault);

                $this->entityManager->flush();
                $this->addFlash('success', 'admin.payment_methods.flash.updated');

                return $this->redirectToRoute('admin_payment_methods_index');
            }
        }

        return $this->render('admin/payment_method/edit.html.twig', [
            'method' => $method,
            'availableProviderNames' => $this->paymentProviderRegistry->getAvailableNames(),
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(PaymentMethod $method, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete_payment_method_' . $method->getId(), (string) $request->request->get('_token'))) {
            $this->entityManager->remove($method);
            $this->entityManager->flush();
            $this->addFlash('success', 'admin.payment_methods.flash.removed');
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }

        return $this->redirectToRoute('admin_payment_methods_index');
    }
}

