<?php

namespace App\Service\Api;

use App\Entity\ApiKey;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class ApiKeyService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Generate a new API key for a user
     */
    public function generateApiKey(User $user, string $name, ?\DateTimeImmutable $expiresAt = null, ?array $scopes = null): string
    {
        // Generate a secure random API key
        $apiKey = bin2hex(random_bytes(32)); // 64 character hex string
        $keyHash = hash('sha256', $apiKey);

        $apiKeyEntity = new ApiKey();
        $apiKeyEntity->setUser($user);
        $apiKeyEntity->setName($name);
        $apiKeyEntity->setKeyHash($keyHash);
        $apiKeyEntity->setExpiresAt($expiresAt);
        $apiKeyEntity->setScopes($scopes);
        $apiKeyEntity->setIsActive(true);

        $this->entityManager->persist($apiKeyEntity);
        $this->entityManager->flush();

        // Return the plain key (only shown once)
        return $apiKey;
    }

    /**
     * Revoke an API key
     */
    public function revokeApiKey(ApiKey $apiKey): void
    {
        $apiKey->setIsActive(false);
        $this->entityManager->flush();
    }

    /**
     * Delete an API key
     */
    public function deleteApiKey(ApiKey $apiKey): void
    {
        $this->entityManager->remove($apiKey);
        $this->entityManager->flush();
    }
}

