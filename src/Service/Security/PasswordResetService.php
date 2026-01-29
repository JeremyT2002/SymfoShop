<?php

namespace App\Service\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PasswordResetService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function requestPasswordReset(string $email): bool
    {
        $user = $this->userRepository->findOneBy(['email' => $email]);

        // Don't reveal if user exists or not (security best practice)
        if (!$user || !$user->isActive()) {
            return false;
        }

        // Generate reset token
        $token = bin2hex(random_bytes(32));
        $expiresAt = new \DateTimeImmutable('+1 hour');

        $user->setResetToken($token);
        $user->setResetTokenExpiresAt($expiresAt);

        $this->entityManager->flush();

        // Send email
        $resetUrl = $this->urlGenerator->generate(
            'reset_password',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $email = (new Email())
            ->from('noreply@symfoshop.com')
            ->to($user->getEmail())
            ->subject('Reset your password')
            ->html($this->getEmailTemplate($user, $resetUrl));

        $this->mailer->send($email);

        return true;
    }

    public function resetPassword(string $token, string $newPassword): ?User
    {
        $user = $this->userRepository->findOneBy(['resetToken' => $token]);

        if (!$user || !$user->isResetTokenValid()) {
            return null;
        }

        // Clear reset token
        $user->setResetToken(null);
        $user->setResetTokenExpiresAt(null);

        return $user;
    }

    private function getEmailTemplate(User $user, string $resetUrl): string
    {
        $name = $user->getFullName() ?: $user->getEmail();

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .button { display: inline-block; padding: 12px 24px; background-color: #2563eb; color: white; text-decoration: none; border-radius: 4px; margin: 20px 0; }
        .button:hover { background-color: #1d4ed8; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Password Reset Request</h1>
        <p>Hello {$name},</p>
        <p>You have requested to reset your password for your SymfoShop account.</p>
        <p>Click the button below to reset your password. This link will expire in 1 hour.</p>
        <a href="{$resetUrl}" class="button">Reset Password</a>
        <p>If you did not request a password reset, please ignore this email.</p>
        <p>Best regards,<br>SymfoShop Team</p>
    </div>
</body>
</html>
HTML;
    }
}

