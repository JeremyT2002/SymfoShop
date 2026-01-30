<?php

namespace App\Controller\Api;

use App\Service\Api\ApiKeyService;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/auth', name: 'api_auth_')]
#[OA\Tag(name: 'Authentication')]
class AuthController extends AbstractController
{
    public function __construct(
        private readonly ApiKeyService $apiKeyService
    ) {
    }

    #[Route('/api-keys', name: 'create_api_key', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Post(
        path: '/api/v1/auth/api-keys',
        summary: 'Create API key',
        description: 'Create a new API key. Requires user authentication (not API key). The API key is only shown once in the response.',
        tags: ['Authentication'],
        security: []
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'name', type: 'string', description: 'API key name'),
                new OA\Property(property: 'expiresAt', type: 'string', format: 'date-time', description: 'Expiration date (optional)'),
                new OA\Property(property: 'scopes', type: 'array', items: new OA\Items(type: 'string'), description: 'API key scopes (optional)'),
            ],
            required: ['name']
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'API key created successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(property: 'message', type: 'string'),
                new OA\Property(
                    property: 'data',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'apiKey', type: 'string', description: 'The API key (only shown once!)'),
                        new OA\Property(property: 'name', type: 'string'),
                        new OA\Property(property: 'expiresAt', type: 'string', format: 'date-time', nullable: true),
                        new OA\Property(property: 'scopes', type: 'array', items: new OA\Items(type: 'string'), nullable: true),
                    ]
                ),
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Invalid request')]
    #[OA\Response(response: 401, description: 'Unauthorized')]
    public function createApiKey(Request $request): JsonResponse
    {
        // This endpoint requires regular user authentication (not API key)
        // Users must be logged in via the web interface to create API keys
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'You must be logged in to create API keys'], Response::HTTP_UNAUTHORIZED);
        }
        $data = json_decode($request->getContent(), true);

        $name = $data['name'] ?? null;
        $expiresAt = isset($data['expiresAt']) ? new \DateTimeImmutable($data['expiresAt']) : null;
        $scopes = $data['scopes'] ?? null;

        if (!$name) {
            return $this->json(['error' => 'Name is required'], Response::HTTP_BAD_REQUEST);
        }

        $apiKey = $this->apiKeyService->generateApiKey($user, $name, $expiresAt, $scopes);

        return $this->json([
            'success' => true,
            'message' => 'API key created successfully',
            'data' => [
                'apiKey' => $apiKey, // Only shown once!
                'name' => $name,
                'expiresAt' => $expiresAt?->format('c'),
                'scopes' => $scopes,
            ],
        ], Response::HTTP_CREATED);
    }

    #[Route('/me', name: 'me', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/auth/me',
        summary: 'Get current user',
        description: 'Get information about the currently authenticated user',
        tags: ['Authentication']
    )]
    #[OA\Response(
        response: 200,
        description: 'User information',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'data',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer'),
                        new OA\Property(property: 'email', type: 'string'),
                        new OA\Property(property: 'firstName', type: 'string', nullable: true),
                        new OA\Property(property: 'lastName', type: 'string', nullable: true),
                        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
                    ]
                ),
            ]
        )
    )]
    #[OA\Response(response: 401, description: 'Unauthorized')]
    public function me(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'data' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'roles' => $user->getRoles(),
            ],
        ]);
    }
}

