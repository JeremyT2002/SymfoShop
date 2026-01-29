<?php

namespace App\Controller\Security;

use App\Form\ResetPasswordFormType;
use App\Form\ResetPasswordRequestFormType;
use App\Service\Security\PasswordResetService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/reset-password')]
class ResetPasswordController extends AbstractController
{
    public function __construct(
        private readonly PasswordResetService $passwordResetService,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    #[Route('', name: 'reset_password_request')]
    public function request(Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('admin');
        }

        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();
            $this->passwordResetService->requestPasswordReset($email);

            $this->addFlash('success', 'If an account exists for that email, a password reset link has been sent.');

            return $this->redirectToRoute('reset_password_request');
        }

        return $this->render('security/reset_password_request.html.twig', [
            'requestForm' => $form,
        ]);
    }

    #[Route('/reset/{token}', name: 'reset_password')]
    public function reset(Request $request, string $token): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('admin');
        }

        $form = $this->createForm(ResetPasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->passwordResetService->resetPassword($token, $form->get('plainPassword')->getData());

            if (!$user) {
                $this->addFlash('error', 'Invalid or expired reset token.');
                return $this->redirectToRoute('reset_password_request');
            }

            // Hash and set new password
            $hashedPassword = $this->passwordHasher->hashPassword($user, $form->get('plainPassword')->getData());
            $user->setPassword($hashedPassword);

            $this->entityManager->flush();

            $this->addFlash('success', 'Your password has been reset successfully. You can now log in.');

            return $this->redirectToRoute('login');
        }

        return $this->render('security/reset_password.html.twig', [
            'resetForm' => $form,
            'token' => $token,
        ]);
    }
}

