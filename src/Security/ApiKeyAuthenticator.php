<?php

namespace App\Security;

use App\Repository\ApiKeyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class ApiKeyAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly ApiKeyRepository $apiKeyRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function supports(Request $request): ?bool
    {
        // Only authenticate API routes
        return str_starts_with($request->getPathInfo(), '/api/');
    }

    public function authenticate(Request $request): Passport
    {
        $apiKey = $this->getApiKey($request);

        if (!$apiKey) {
            throw new CustomUserMessageAuthenticationException('API key is required');
        }

        $apiKeyEntity = $this->apiKeyRepository->findByKeyHash(hash('sha256', $apiKey));

        if (!$apiKeyEntity || !$apiKeyEntity->isValid()) {
            throw new CustomUserMessageAuthenticationException('Invalid or expired API key');
        }

        // Update last used timestamp
        $apiKeyEntity->setLastUsedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        // Store API key in token for later use
        $user = $apiKeyEntity->getUser();
        $user->setApiKey($apiKeyEntity);

        return new SelfValidatingPassport(
            new UserBadge($user->getUserIdentifier(), function () use ($user) {
                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null; // Let the request continue
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'error' => 'Authentication failed',
            'message' => $exception->getMessage(),
        ], Response::HTTP_UNAUTHORIZED);
    }

    private function getApiKey(Request $request): ?string
    {
        // Check Authorization header: Bearer <token> or X-API-Key: <token>
        $authHeader = $request->headers->get('Authorization');
        if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        // Check X-API-Key header
        $apiKey = $request->headers->get('X-API-Key');
        if ($apiKey) {
            return $apiKey;
        }

        // Check query parameter (less secure, but sometimes needed)
        return $request->query->get('api_key');
    }
}

