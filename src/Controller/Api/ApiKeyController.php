<?php

namespace App\Controller\Api;

use App\Entity\ApiKey;
use App\Repository\ApiKeyRepository;
use App\Service\Api\ApiKeyService;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/api-keys', name: 'api_api_keys_')]
#[OA\Tag(name: 'API Keys')]
#[Security(name: 'BearerAuth')]
class ApiKeyController extends AbstractController
{
    public function __construct(
        private readonly ApiKeyRepository $apiKeyRepository,
        private readonly ApiKeyService $apiKeyService
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/api-keys',
        summary: 'List API keys',
        description: 'Get a list of all active API keys for the current user',
        tags: ['API Keys']
    )]
    #[OA\Response(
        response: 200,
        description: 'List of API keys',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
            ]
        )
    )]
    #[OA\Response(response: 401, description: 'Unauthorized')]
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
    #[OA\Delete(
        path: '/api/v1/api-keys/{id}',
        summary: 'Revoke API key',
        description: 'Revoke (deactivate) an API key by ID',
        tags: ['API Keys']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'API key ID',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'API key revoked successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(property: 'message', type: 'string'),
            ]
        )
    )]
    #[OA\Response(response: 401, description: 'Unauthorized')]
    #[OA\Response(response: 404, description: 'API key not found')]
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

