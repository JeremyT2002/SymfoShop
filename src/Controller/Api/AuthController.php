<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\Api\ApiKeyService;
use Nelmio\ApiDocBundle\Attribute\Security as DocSecurity;
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
    use ApiResponderTrait;

    public function __construct(
        private readonly ApiKeyService $apiKeyService
    ) {
    }

    #[Route('/api-keys', name: 'create_api_key', methods: ['POST'])]
    #[IsGranted('ROLE_USER', statusCode: Response::HTTP_UNAUTHORIZED)]
    #[OA\Post(
        path: '/api/v1/auth/api-keys',
        summary: 'Create API key',
        description: 'Creates a new API key for the **logged-in shop user** (session cookie). Not available with API key authentication. The plain key is returned only once.',
        tags: ['Authentication'],
        security: []
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'name', type: 'string', description: 'API key label'),
                new OA\Property(property: 'expiresAt', type: 'string', format: 'date-time', description: 'Optional expiry (ISO 8601)'),
                new OA\Property(property: 'scopes', type: 'array', items: new OA\Items(type: 'string'), description: 'Optional scopes'),
            ],
            required: ['name']
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'API key created',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'data', type: 'object'),
                new OA\Property(property: 'meta', type: 'object', properties: [new OA\Property(property: 'message', type: 'string')]),
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Invalid request', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 401, description: 'Not logged in to the shop')]
    public function createApiKey(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->apiError('Authentication required', Response::HTTP_UNAUTHORIZED);
        }

        $data = $this->apiJsonBody($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $name = isset($data['name']) ? trim((string) $data['name']) : '';
        if ('' === $name) {
            return $this->apiError('Name is required', Response::HTTP_BAD_REQUEST, 'INVALID_BODY');
        }

        $expiresAt = null;
        if (!empty($data['expiresAt'])) {
            try {
                $expiresAt = new \DateTimeImmutable((string) $data['expiresAt']);
            } catch (\Exception) {
                return $this->apiError('Invalid expiresAt value', Response::HTTP_BAD_REQUEST, 'INVALID_BODY');
            }
        }

        /** @var list<string>|null $scopes */
        $scopes = $data['scopes'] ?? null;
        if (null !== $scopes && !\is_array($scopes)) {
            return $this->apiError('scopes must be an array of strings', Response::HTTP_BAD_REQUEST, 'INVALID_BODY');
        }

        $plainKey = $this->apiKeyService->generateApiKey($user, $name, $expiresAt, $scopes);

        return $this->apiData(
            [
                'apiKey' => $plainKey,
                'name' => $name,
                'expiresAt' => $expiresAt?->format('c'),
                'scopes' => $scopes,
            ],
            Response::HTTP_CREATED,
            ['message' => 'API key created successfully']
        );
    }

    #[Route('/me', name: 'me', methods: ['GET'])]
    #[DocSecurity(name: 'BearerAuth')]
    #[OA\Get(
        path: '/api/v1/auth/me',
        summary: 'Current user (API key)',
        description: 'Returns the user tied to the API key used for this request.',
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
    #[OA\Response(response: 401, description: 'Unauthorized', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function me(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->apiError('Unauthorized', Response::HTTP_UNAUTHORIZED);
        }

        return $this->apiData([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'roles' => $user->getRoles(),
        ]);
    }
}
