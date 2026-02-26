<?php

namespace App\Controller\Admin;

use App\Entity\Coupon;
use App\Entity\CouponType;
use App\Repository\CouponRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/coupons', name: 'admin_coupons_')]
class CouponController extends AbstractController
{
    public function __construct(
        private readonly CouponRepository $couponRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $coupons = $this->couponRepository->findBy([], ['createdAt' => 'DESC']);
        
        return $this->render('admin/coupon/index.html.twig', [
            'coupons' => $coupons,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $coupon = new Coupon();
        
        if ($request->isMethod('POST')) {
            $code = strtoupper(trim($request->request->get('code', '')));
            $type = $request->request->get('type');
            $value = (int) $request->request->get('value', 0);
            $expiresAt = $request->request->get('expiresAt');
            $usageLimit = $request->request->get('usageLimit');
            $perUserLimit = $request->request->get('perUserLimit');
            $isActive = $request->request->get('isActive') === '1';
            
            // Validate code uniqueness
            $existing = $this->couponRepository->findByCode($code);
            if ($existing) {
                $this->addFlash('error', 'A coupon with this code already exists.');
                return $this->render('admin/coupon/new.html.twig', [
                    'coupon' => $coupon,
                ]);
            }
            
            $coupon->setCode($code);
            $coupon->setType(CouponType::from($type));
            $coupon->setValue($value);
            
            if ($expiresAt) {
                $coupon->setExpiresAt(new \DateTimeImmutable($expiresAt));
            }
            
            if ($usageLimit !== null && $usageLimit !== '') {
                $coupon->setUsageLimit((int) $usageLimit);
            }
            
            if ($perUserLimit !== null && $perUserLimit !== '') {
                $coupon->setPerUserLimit((int) $perUserLimit);
            }
            
            $coupon->setIsActive($isActive);
            $coupon->setUpdatedAt(new \DateTimeImmutable());
            
            $this->entityManager->persist($coupon);
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Coupon created successfully.');
            
            return $this->redirectToRoute('admin_coupons_show', ['id' => $coupon->getId()]);
        }
        
        return $this->render('admin/coupon/new.html.twig', [
            'coupon' => $coupon,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $coupon = $this->couponRepository->find($id);
        
        if (!$coupon) {
            throw $this->createNotFoundException('Coupon not found');
        }
        
        $totalUsages = $this->couponRepository->countUsages($coupon);
        
        return $this->render('admin/coupon/show.html.twig', [
            'coupon' => $coupon,
            'totalUsages' => $totalUsages,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(int $id, Request $request): Response
    {
        $coupon = $this->couponRepository->find($id);
        
        if (!$coupon) {
            throw $this->createNotFoundException('Coupon not found');
        }
        
        if ($request->isMethod('POST')) {
            $code = strtoupper(trim($request->request->get('code', '')));
            $type = $request->request->get('type');
            $value = (int) $request->request->get('value', 0);
            $expiresAt = $request->request->get('expiresAt');
            $usageLimit = $request->request->get('usageLimit');
            $perUserLimit = $request->request->get('perUserLimit');
            $isActive = $request->request->get('isActive') === '1';
            
            // Validate code uniqueness (except for current coupon)
            if ($code !== $coupon->getCode()) {
                $existing = $this->couponRepository->findByCode($code);
                if ($existing && $existing->getId() !== $coupon->getId()) {
                    $this->addFlash('error', 'A coupon with this code already exists.');
                    return $this->render('admin/coupon/edit.html.twig', [
                        'coupon' => $coupon,
                    ]);
                }
            }
            
            $coupon->setCode($code);
            $coupon->setType(CouponType::from($type));
            $coupon->setValue($value);
            
            if ($expiresAt) {
                $coupon->setExpiresAt(new \DateTimeImmutable($expiresAt));
            } else {
                $coupon->setExpiresAt(null);
            }
            
            if ($usageLimit !== null && $usageLimit !== '') {
                $coupon->setUsageLimit((int) $usageLimit);
            } else {
                $coupon->setUsageLimit(null);
            }
            
            if ($perUserLimit !== null && $perUserLimit !== '') {
                $coupon->setPerUserLimit((int) $perUserLimit);
            } else {
                $coupon->setPerUserLimit(null);
            }
            
            $coupon->setIsActive($isActive);
            $coupon->setUpdatedAt(new \DateTimeImmutable());
            
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Coupon updated successfully.');
            
            return $this->redirectToRoute('admin_coupons_show', ['id' => $coupon->getId()]);
        }
        
        return $this->render('admin/coupon/edit.html.twig', [
            'coupon' => $coupon,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(int $id, Request $request): Response
    {
        $coupon = $this->couponRepository->find($id);
        
        if (!$coupon) {
            throw $this->createNotFoundException('Coupon not found');
        }
        
        if ($this->isCsrfTokenValid('delete_coupon_' . $coupon->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($coupon);
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Coupon deleted successfully.');
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }
        
        return $this->redirectToRoute('admin_coupons_index');
    }
}

