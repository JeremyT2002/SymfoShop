<?php

namespace App\Controller\Catalog;

use App\Entity\Product;
use App\Entity\ProductReview;
use App\Entity\User;
use App\Repository\ProductRepository;
use App\Repository\ProductReviewRepository;
use App\Service\Review\VerifiedPurchaseChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class ProductController extends AbstractController
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly ProductReviewRepository $productReviewRepository,
        private readonly VerifiedPurchaseChecker $verifiedPurchaseChecker,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/product/{slug}', name: 'catalog_product', methods: ['GET', 'POST'])]
    public function show(string $slug, Request $request): Response
    {
        $product = $this->productRepository->findOneBySlug($slug);

        if (!$product) {
            throw new NotFoundHttpException('Product not found');
        }

        if ($request->isMethod('POST')) {
            $this->handleReviewSubmission($request, $product);

            return $this->redirectToRoute('catalog_product', ['slug' => $product->getSlug()]);
        }

        $variants = $product->getVariants()->toArray();
        $defaultVariant = !empty($variants) ? $variants[0] : null;
        $reviewPage = max(1, $request->query->getInt('reviewPage', 1));
        $reviewsPerPage = 5;
        $reviewOffset = ($reviewPage - 1) * $reviewsPerPage;
        $approvedReviews = $this->productReviewRepository->findApprovedByProductPaginated($product, $reviewsPerPage, $reviewOffset);
        $approvedReviewCount = $this->productReviewRepository->countApprovedByProduct($product);
        $reviewPages = max(1, (int) ceil($approvedReviewCount / $reviewsPerPage));
        $user = $this->getUser();
        $canReview = false;
        $hasExistingReview = false;
        if ($user instanceof User) {
            $hasExistingReview = $this->productReviewRepository->hasReviewForProductAndUser($product, $user);
            $canReview = !$hasExistingReview && $this->verifiedPurchaseChecker->hasVerifiedPurchase($user, $product);
        }

        return $this->render('catalog/product/show.html.twig', [
            'product' => $product,
            'variants' => $variants,
            'defaultVariant' => $defaultVariant,
            'approvedReviews' => $approvedReviews,
            'reviewCount' => $approvedReviewCount,
            'reviewPage' => $reviewPage,
            'reviewPages' => $reviewPages,
            'canReview' => $canReview,
            'hasExistingReview' => $hasExistingReview,
        ]);
    }

    private function handleReviewSubmission(Request $request, Product $product): void
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'review.flash.login_required');
            return;
        }

        if (!$this->isCsrfTokenValid('product_review_' . $product->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'review.flash.invalid_csrf');
            return;
        }

        if ($this->productReviewRepository->hasReviewForProductAndUser($product, $user)) {
            $this->addFlash('error', 'review.flash.already_exists');
            return;
        }

        if (!$this->verifiedPurchaseChecker->hasVerifiedPurchase($user, $product)) {
            $this->addFlash('error', 'review.flash.verified_purchase_required');
            return;
        }

        $rating = (int) $request->request->get('rating', 0);
        $title = trim((string) $request->request->get('title', ''));
        $body = trim((string) $request->request->get('body', ''));
        if ($rating < 1 || $rating > 5 || $title === '' || $body === '') {
            $this->addFlash('error', 'review.flash.invalid_payload');
            return;
        }

        $review = (new ProductReview())
            ->setProduct($product)
            ->setUser($user)
            ->setRating($rating)
            ->setTitle($title)
            ->setBody($body)
            ->setIsVerifiedPurchase(true)
            ->setIsApproved(false);

        $this->entityManager->persist($review);
        $this->entityManager->flush();

        $this->addFlash('success', 'review.flash.submitted');
    }
}

