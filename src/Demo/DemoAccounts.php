<?php

declare(strict_types=1);

namespace App\Demo;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * One-click demo logins for a public demo instance (DEMO_MODE=1).
 *
 * The accounts are configured in config/services.yaml (app.demo_accounts). They are signed in
 * without a password, so visitors changing a demo password or enabling 2FA cannot lock others out.
 * With DEMO_MODE off this class reports no accounts and refuses to touch any user.
 */
final class DemoAccounts
{
    /**
     * @param array<string, array{email: string, roles: list<string>, first_name: string, last_name: string, target_route: string}> $accounts
     */
    public function __construct(
        #[Autowire('%app.demo_mode%')]
        private readonly bool $enabled,
        #[Autowire('%app.demo_accounts%')]
        private readonly array $accounts,
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @return array<string, array{email: string, roles: list<string>, first_name: string, last_name: string, target_route: string}>
     */
    public function all(): array
    {
        return $this->enabled ? $this->accounts : [];
    }

    public function has(string $key): bool
    {
        return isset($this->all()[$key]);
    }

    public function targetRoute(string $key): string
    {
        return $this->account($key)['target_route'];
    }

    /**
     * Returns the demo user, creating it if needed, and puts it back into a usable state:
     * configured roles, active, verified and without two-factor login.
     */
    public function ensure(string $key): User
    {
        $account = $this->account($key);

        $user = $this->users->findOneBy(['email' => $account['email']]);
        if ($user === null) {
            $user = (new User())
                ->setEmail($account['email'])
                ->setFirstName($account['first_name'])
                ->setLastName($account['last_name']);
            // Nobody signs in with this password – demo logins go through DemoLoginController
            $user->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(24))));
            $this->entityManager->persist($user);
        }

        $user->setRoles($account['roles']);
        $user->setIsActive(true);
        $user->setIsVerified(true);
        $user->setTotpSecret(null);
        $this->entityManager->flush();

        return $user;
    }

    /**
     * @return array{email: string, roles: list<string>, first_name: string, last_name: string, target_route: string}
     */
    private function account(string $key): array
    {
        if (!$this->has($key)) {
            throw new \InvalidArgumentException(sprintf('Unknown demo account "%s" or DEMO_MODE is off.', $key));
        }

        return $this->accounts[$key];
    }
}
