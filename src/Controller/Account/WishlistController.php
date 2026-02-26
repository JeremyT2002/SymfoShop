<?php

namespace App\Controller\Account;

use App\Entity\Product;
use App\Entity\Wishlist;
use App\Repository\ProductRepository;
use App\Repository\WishlistRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/account/wishlist', name: 'account_wishlist_')]
#[IsGranted('ROLE_USER')]
class WishlistController extends AbstractController
{
    public function __construct(
        private readonly WishlistRepository $wishlistRepository,
        private readonly ProductRepository $productRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    #[Route('', name: 'show', methods: ['GET'])]
    public function show(): Response
    {
        $user = $this->getUser();
        $wishlistItems = $this->wishlistRepository->findByUser($user);
        $products = array_map(fn(Wishlist $item) => $item->getProduct(), $wishlistItems);

        return $this->render('account/wishlist/show.html.twig', [
            'products' => $products,
            'wishlistCount' => count($products),
        ]);
    }

    #[Route('/toggle', name: 'toggle', methods: ['POST'])]
    public function toggle(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $productId = (int) $request->request->get('productId');

        if ($productId <= 0) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid product ID',
            ], Response::HTTP_BAD_REQUEST);
        }

        $product = $this->productRepository->find($productId);
        if (!$product) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Product not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $wishlistItem = $this->wishlistRepository->findOneByUserAndProduct($user, $product);

        if ($wishlistItem) {
            // Remove from wishlist
            $this->entityManager->remove($wishlistItem);
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'inWishlist' => false,
                'message' => 'Product removed from wishlist',
            ]);
        } else {
            // Add to wishlist
            $wishlistItem = new Wishlist();
            $wishlistItem->setUser($user);
            $wishlistItem->setProduct($product);

            $this->entityManager->persist($wishlistItem);
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'inWishlist' => true,
                'message' => 'Product added to wishlist',
            ]);
        }
    }

    #[Route('/check', name: 'check', methods: ['GET'])]
    public function check(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $productId = (int) $request->query->get('productId');

        if ($productId <= 0) {
            return new JsonResponse([
                'success' => false,
                'inWishlist' => false,
            ], Response::HTTP_BAD_REQUEST);
        }

        $product = $this->productRepository->find($productId);
        if (!$product) {
            return new JsonResponse([
                'success' => false,
                'inWishlist' => false,
            ], Response::HTTP_NOT_FOUND);
        }

        $inWishlist = $this->wishlistRepository->isProductInWishlist($user, $product);

        return new JsonResponse([
            'success' => true,
            'inWishlist' => $inWishlist,
        ]);
    }
}

