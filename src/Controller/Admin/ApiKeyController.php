<?php

namespace App\Controller\Admin;

use App\Entity\ApiKey;
use App\Repository\ApiKeyRepository;
use App\Service\Api\ApiKeyService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/api-keys', name: 'admin_api_keys_')]
class ApiKeyController extends AbstractController
{
    public function __construct(
        private readonly ApiKeyRepository $apiKeyRepository,
        private readonly ApiKeyService $apiKeyService,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        $isActive = $request->query->get('isActive');
        
        $criteria = [];
        if ($isActive !== null && $isActive !== '') {
            $criteria['isActive'] = $isActive === '1';
        }
        
        $apiKeys = $this->apiKeyRepository->findBy(
            $criteria,
            ['createdAt' => 'DESC'],
            $limit,
            $offset
        );
        
        $total = $this->apiKeyRepository->count($criteria);
        
        return $this->render('admin/api_key/index.html.twig', [
            'apiKeys' => $apiKeys,
            'currentPage' => $page,
            'totalPages' => ceil($total / $limit),
            'isActive' => $isActive,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $name = $request->request->get('name');
            $userId = $request->request->get('user');
            $expiresAt = $request->request->get('expiresAt') ? new \DateTimeImmutable($request->request->get('expiresAt')) : null;
            $scopes = $request->request->get('scopes', '');
            
            $user = $this->entityManager->getRepository(\App\Entity\User::class)->find($userId);
            
            if (!$user) {
                $this->addFlash('error', 'User not found.');
                return $this->redirectToRoute('admin_api_keys_new');
            }
            
            $plainKey = $this->apiKeyService->generateApiKey($user, $name, $expiresAt, $scopes ? explode(',', $scopes) : []);
            
            // Get the created API key entity (it was just persisted)
            $apiKey = $this->apiKeyRepository->findOneBy(['user' => $user, 'name' => $name], ['createdAt' => 'DESC']);
            
            if (!$apiKey) {
                $this->addFlash('error', 'Failed to create API key.');
                return $this->redirectToRoute('admin_api_keys_new');
            }
            
            // Store plain key in session to show once
            $request->getSession()->set('api_key_' . $apiKey->getId(), $plainKey);
            
            $this->addFlash('success', 'API key created successfully. Make sure to copy it now - it will not be shown again!');
            
            return $this->redirectToRoute('admin_api_keys_show', ['id' => $apiKey->getId()]);
        }
        
        $users = $this->entityManager->getRepository(\App\Entity\User::class)->findAll();
        
        return $this->render('admin/api_key/new.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id, Request $request): Response
    {
        $apiKey = $this->apiKeyRepository->find($id);
        
        if (!$apiKey) {
            throw $this->createNotFoundException('API key not found');
        }
        
        // Check if we have the plain key in session (only shown once after creation)
        $plainKey = $request->getSession()->get('api_key_' . $apiKey->getId());
        if ($plainKey) {
            // Remove from session after showing
            $request->getSession()->remove('api_key_' . $apiKey->getId());
        }
        
        return $this->render('admin/api_key/show.html.twig', [
            'apiKey' => $apiKey,
            'plainKey' => $plainKey,
        ]);
    }

    #[Route('/{id}/revoke', name: 'revoke', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function revoke(int $id, Request $request): Response
    {
        $apiKey = $this->apiKeyRepository->find($id);
        
        if (!$apiKey) {
            throw $this->createNotFoundException('API key not found');
        }
        
        if ($this->isCsrfTokenValid('revoke_api_key_' . $apiKey->getId(), $request->request->get('_token'))) {
            $this->apiKeyService->revokeApiKey($apiKey);
            $this->entityManager->flush();
            
            $this->addFlash('success', 'API key revoked successfully.');
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }
        
        return $this->redirectToRoute('admin_api_keys_show', ['id' => $apiKey->getId()]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(int $id, Request $request): Response
    {
        $apiKey = $this->apiKeyRepository->find($id);
        
        if (!$apiKey) {
            throw $this->createNotFoundException('API key not found');
        }
        
        if ($this->isCsrfTokenValid('delete_api_key_' . $apiKey->getId(), $request->request->get('_token'))) {
            $this->apiKeyService->deleteApiKey($apiKey);
            $this->entityManager->flush();
            
            $this->addFlash('success', 'API key deleted successfully.');
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }
        
        return $this->redirectToRoute('admin_api_keys_index');
    }
}

