<?php

namespace App\Controller\Api;

use App\Service\Api\ApiKeyService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/auth', name: 'api_auth_')]
class AuthController extends AbstractController
{
    public function __construct(
        private readonly ApiKeyService $apiKeyService
    ) {
    }

    #[Route('/api-keys', name: 'create_api_key', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
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

