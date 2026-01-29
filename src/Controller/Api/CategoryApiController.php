<?php

namespace App\Controller\Api;

use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/categories', name: 'api_categories_')]
class CategoryApiController extends AbstractController
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $categories = $this->categoryRepository->findBy(['parent' => null], ['name' => 'ASC']);

        $data = array_map(fn($category) => $this->serializeCategory($category, true), $categories);

        return $this->json(['data' => $data]);
    }

    #[Route('/{slug}', name: 'show', methods: ['GET'])]
    public function show(string $slug): JsonResponse
    {
        $category = $this->categoryRepository->findOneBy(['slug' => $slug]);

        if (!$category) {
            return $this->json(['error' => 'Category not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(['data' => $this->serializeCategory($category, true)]);
    }

    private function serializeCategory($category, bool $includeChildren = false): array
    {
        $data = [
            'id' => $category->getId(),
            'name' => $category->getName(),
            'slug' => $category->getSlug(),
        ];

        if ($category->getParent()) {
            $data['parent'] = [
                'id' => $category->getParent()->getId(),
                'name' => $category->getParent()->getName(),
                'slug' => $category->getParent()->getSlug(),
            ];
        }

        if ($includeChildren && $category->getChildren()->count() > 0) {
            $data['children'] = array_map(
                fn($child) => $this->serializeCategory($child, false),
                $category->getChildren()->toArray()
            );
        }

        return $data;
    }
}

