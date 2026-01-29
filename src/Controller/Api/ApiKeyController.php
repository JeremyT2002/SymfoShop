<?php

namespace App\Controller\Api;

use App\Entity\ApiKey;
use App\Repository\ApiKeyRepository;
use App\Service\Api\ApiKeyService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/api-keys', name: 'api_api_keys_')]
class ApiKeyController extends AbstractController
{
    public function __construct(
        private readonly ApiKeyRepository $apiKeyRepository,
        private readonly ApiKeyService $apiKeyService
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $apiKeys = $this->apiKeyRepository->findActiveKeysForUser($user->getId());

        $data = array_map(fn(ApiKey $key) => [
            'id' => $key->getId(),
            'name' => $key->getName(),
            'createdAt' => $key->getCreatedAt()->format('c'),
            'expiresAt' => $key->getExpiresAt()?->format('c'),
            'lastUsedAt' => $key->getLastUsedAt()?->format('c'),
            'scopes' => $key->getScopes(),
        ], $apiKeys);

        return $this->json(['data' => $data]);
    }


    #[Route('/{id}', name: 'revoke', methods: ['DELETE'])]
    public function revoke(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $apiKey = $this->apiKeyRepository->find($id);

        if (!$apiKey) {
            return $this->json(['error' => 'API key not found'], Response::HTTP_NOT_FOUND);
        }

        // Check if user owns this API key
        if ($apiKey->getUser()->getId() !== $user->getId() && !in_array('ROLE_ADMIN', $user->getRoles())) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $this->apiKeyService->revokeApiKey($apiKey);

        return $this->json([
            'success' => true,
            'message' => 'API key revoked successfully',
        ]);
    }
}

