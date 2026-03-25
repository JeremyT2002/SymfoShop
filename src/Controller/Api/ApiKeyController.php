<?php

namespace App\Controller\Api;

use App\Entity\ApiKey;
use App\Entity\User;
use App\Repository\ApiKeyRepository;
use App\Service\Api\ApiKeyService;
use Nelmio\ApiDocBundle\Attribute\Security as DocSecurity;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/api-keys', name: 'api_api_keys_')]
#[OA\Tag(name: 'API Keys')]
#[DocSecurity(name: 'BearerAuth')]
class ApiKeyController extends AbstractController
{
    use ApiResponderTrait;

    public function __construct(
        private readonly ApiKeyRepository $apiKeyRepository,
        private readonly ApiKeyService $apiKeyService
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/api-keys',
        summary: 'List API keys',
        description: 'Active API keys for the authenticated user (metadata only; never the secret).',
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
    #[OA\Response(response: 401, description: 'Unauthorized', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function list(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->apiError('Unauthorized', Response::HTTP_UNAUTHORIZED);
        }

        $apiKeys = $this->apiKeyRepository->findActiveKeysForUser($user->getId());

        $data = array_map(fn (ApiKey $key) => [
            'id' => $key->getId(),
            'name' => $key->getName(),
            'createdAt' => $key->getCreatedAt()->format('c'),
            'expiresAt' => $key->getExpiresAt()?->format('c'),
            'lastUsedAt' => $key->getLastUsedAt()?->format('c'),
            'scopes' => $key->getScopes(),
        ], $apiKeys);

        return $this->apiData($data);
    }

    #[Route('/{id}', name: 'revoke', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/v1/api-keys/{id}',
        summary: 'Revoke API key',
        description: 'Deactivates an API key owned by the current user.',
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
        description: 'Key revoked',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'data', type: 'object', properties: [new OA\Property(property: 'revoked', type: 'boolean')]),
                new OA\Property(property: 'meta', type: 'object', properties: [new OA\Property(property: 'message', type: 'string')]),
            ]
        )
    )]
    #[OA\Response(response: 401, description: 'Unauthorized', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function revoke(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->apiError('Unauthorized', Response::HTTP_UNAUTHORIZED);
        }

        $apiKey = $this->apiKeyRepository->find($id);

        if (!$apiKey) {
            return $this->apiError('API key not found', Response::HTTP_NOT_FOUND);
        }

        if ($apiKey->getUser()->getId() !== $user->getId() && !\in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return $this->apiError('Access denied', Response::HTTP_FORBIDDEN);
        }

        $this->apiKeyService->revokeApiKey($apiKey);

        return $this->apiData(['revoked' => true], Response::HTTP_OK, ['message' => 'API key revoked']);
    }
}
