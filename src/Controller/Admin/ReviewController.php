<?php

namespace App\Controller\Admin;

use App\Entity\ProductReview;
use App\Repository\ProductReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/reviews', name: 'admin_reviews_')]
#[IsGranted('ROLE_ADMIN')]
class ReviewController extends AbstractController
{
    public function __construct(
        private readonly ProductReviewRepository $productReviewRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $filter = (string) $request->query->get('filter', 'pending');
        $approved = match ($filter) {
            'approved' => true,
            'all' => null,
            default => false,
        };
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;
        $reviews = $this->productReviewRepository->findForAdminList($approved, $limit, $offset);
        $total = $this->productReviewRepository->countForAdminList($approved);
        $totalPages = max(1, (int) ceil($total / $limit));

        return $this->render('admin/review/index.html.twig', [
            'reviews' => $reviews,
            'filter' => $filter,
            'page' => $page,
            'totalPages' => $totalPages,
            'pendingCount' => $this->productReviewRepository->countPending(),
        ]);
    }

    #[Route('/{id}/approve', name: 'approve', methods: ['POST'])]
    public function approve(ProductReview $review, Request $request): RedirectResponse
    {
        $this->assertCsrf($request, 'admin_review_approve_' . $review->getId());
        $review->setIsApproved(true);
        $this->entityManager->flush();
        $this->addFlash('success', 'admin.reviews.flash.approved');

        return $this->redirectToRoute('admin_reviews_index', $this->getRedirectQuery($request));
    }

    #[Route('/{id}/reject', name: 'reject', methods: ['POST'])]
    public function reject(ProductReview $review, Request $request): RedirectResponse
    {
        $this->assertCsrf($request, 'admin_review_reject_' . $review->getId());
        $review->setIsApproved(false);
        $this->entityManager->flush();
        $this->addFlash('success', 'admin.reviews.flash.rejected');

        return $this->redirectToRoute('admin_reviews_index', $this->getRedirectQuery($request));
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(ProductReview $review, Request $request): RedirectResponse
    {
        $this->assertCsrf($request, 'admin_review_delete_' . $review->getId());
        $this->entityManager->remove($review);
        $this->entityManager->flush();
        $this->addFlash('success', 'admin.reviews.flash.deleted');

        return $this->redirectToRoute('admin_reviews_index', $this->getRedirectQuery($request));
    }

    #[Route('/bulk', name: 'bulk', methods: ['POST'])]
    public function bulk(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('bulk_reviews', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $ids = $this->parseBulkIds((string) $request->request->get('ids', ''));
        $action = (string) $request->request->get('action', '');
        if ($ids === [] || !in_array($action, ['approve', 'reject'], true)) {
            $this->addFlash('error', 'Invalid bulk action payload.');

            return $this->redirectToRoute('admin_reviews_index', $this->getRedirectQuery($request));
        }

        $reviews = $this->productReviewRepository->findBy(['id' => $ids]);
        foreach ($reviews as $review) {
            $review->setIsApproved($action === 'approve');
        }
        $this->entityManager->flush();
        $this->addFlash('success', sprintf('Updated %d review(s).', count($reviews)));

        return $this->redirectToRoute('admin_reviews_index', $this->getRedirectQuery($request));
    }

    private function assertCsrf(Request $request, string $tokenId): void
    {
        if (!$this->isCsrfTokenValid($tokenId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }

    /**
     * @return array{filter:string,page:int}
     */
    private function getRedirectQuery(Request $request): array
    {
        return [
            'filter' => (string) $request->request->get('filter', 'pending'),
            'page' => max(1, (int) $request->request->get('page', 1)),
        ];
    }

    /**
     * @return list<int>
     */
    private function parseBulkIds(string $raw): array
    {
        $parts = array_filter(array_map('trim', explode(',', $raw)), static fn (string $value): bool => $value !== '');

        return array_values(array_map('intval', $parts));
    }
}

