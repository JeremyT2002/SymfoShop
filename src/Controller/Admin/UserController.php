<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/users', name: 'admin_users_')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = in_array((int) $request->query->get('perPage', 25), [25, 50, 100], true)
            ? (int) $request->query->get('perPage', 25)
            : 25;
        $offset = ($page - 1) * $limit;
        
        $search = trim((string) $request->query->get('search', ''));
        $role = (string) $request->query->get('role', '');
        $sortBy = (string) $request->query->get('sortBy', 'createdAt');
        $sortDir = strtoupper((string) $request->query->get('sortDir', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $users = $this->userRepository->findForAdminList(
            $search !== '' ? $search : null,
            $role !== '' ? $role : null,
            $limit,
            $offset,
            $sortBy,
            $sortDir
        );
        $total = $this->userRepository->countForAdminList(
            $search !== '' ? $search : null,
            $role !== '' ? $role : null
        );
        
        return $this->render('admin/user/index.html.twig', [
            'users' => $users,
            'currentPage' => $page,
            'totalPages' => ceil($total / $limit),
            'search' => $search,
            'role' => $role,
            'perPage' => $limit,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $user = new User();
        
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $password = $request->request->get('password');
            $roles = $request->request->get('roles', []);
            $isActive = $request->request->get('isActive') === '1';
            
            // Check if email already exists
            if ($this->userRepository->findOneBy(['email' => $email])) {
                $this->addFlash('error', 'A user with this email already exists.');
                return $this->render('admin/user/new.html.twig', [
                    'user' => $user,
                ]);
            }
            
            $user->setEmail($email);
            $user->setPassword($this->passwordHasher->hashPassword($user, $password));
            $user->setRoles($roles);
            $user->setIsActive($isActive);
            
            $this->entityManager->persist($user);
            $this->entityManager->flush();
            
            $this->addFlash('success', 'User created successfully.');
            
            return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
        }
        
        return $this->render('admin/user/new.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $user = $this->userRepository->find($id);
        
        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }
        
        return $this->render('admin/user/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(int $id, Request $request): Response
    {
        $user = $this->userRepository->find($id);
        
        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }
        
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $password = $request->request->get('password');
            $roles = $request->request->get('roles', []);
            $isActive = $request->request->get('isActive') === '1';
            
            // Check if email is changed and already exists
            if ($email !== $user->getEmail() && $this->userRepository->findOneBy(['email' => $email])) {
                $this->addFlash('error', 'A user with this email already exists.');
                return $this->render('admin/user/edit.html.twig', [
                    'user' => $user,
                ]);
            }
            
            $user->setEmail($email);
            if ($password) {
                $user->setPassword($this->passwordHasher->hashPassword($user, $password));
            }
            $user->setRoles($roles);
            $user->setIsActive($isActive);
            
            $this->entityManager->flush();
            
            $this->addFlash('success', 'User updated successfully.');
            
            return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
        }
        
        return $this->render('admin/user/edit.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(int $id, Request $request): Response
    {
        $user = $this->userRepository->find($id);
        
        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }
        
        // Prevent deleting yourself
        if ($user->getId() === $this->getUser()?->getId()) {
            $this->addFlash('error', 'You cannot delete your own account.');
            return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
        }
        
        if ($this->isCsrfTokenValid('delete_user_' . $user->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($user);
            $this->entityManager->flush();
            
            $this->addFlash('success', 'User deleted successfully.');
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }
        
        return $this->redirectToRoute('admin_users_index');
    }

    #[Route('/{id}/active', name: 'update_active', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function updateActive(int $id, Request $request): Response
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        if (!$this->isCsrfTokenValid('update_user_active_' . $user->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');

            return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
        }

        $active = match ((string) $request->request->get('active', '')) {
            '1' => true,
            '0' => false,
            default => null,
        };
        if ($active === null) {
            $this->addFlash('error', 'Invalid active value.');

            return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
        }

        if (!$active && $user->getId() === $this->getUser()?->getId()) {
            $this->addFlash('error', 'You cannot deactivate your own account.');

            return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
        }

        $user->setIsActive($active);
        $this->entityManager->flush();
        $this->addFlash('success', 'User status updated.');

        return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
    }

    #[Route('/bulk/active', name: 'bulk_active', methods: ['POST'])]
    public function bulkActive(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('bulk_users_active', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');

            return $this->redirectToRoute('admin_users_index');
        }

        $ids = $this->parseBulkIds((string) $request->request->get('ids', ''));
        $active = match ((string) $request->request->get('active', '')) {
            '1' => true,
            '0' => false,
            default => null,
        };

        if ($ids === [] || $active === null) {
            $this->addFlash('error', 'Invalid bulk action payload.');

            return $this->redirectToRoute('admin_users_index');
        }

        $users = $this->userRepository->findBy(['id' => $ids]);
        $selfId = $this->getUser()?->getId();
        $updated = 0;
        foreach ($users as $user) {
            if (!$active && $selfId !== null && $user->getId() === $selfId) {
                continue;
            }

            $user->setIsActive($active);
            $updated++;
        }
        $this->entityManager->flush();
        $this->addFlash('success', sprintf('Updated %d user(s).', $updated));

        return $this->redirectToRoute('admin_users_index');
    }

    /**
     * @return list<int>
     */
    private function parseBulkIds(string $raw): array
    {
        $parts = array_filter(array_map('trim', explode(',', $raw)), static fn (string $value): bool => $value !== '');

        return array_values(array_map('intval', $parts));
    }
}

