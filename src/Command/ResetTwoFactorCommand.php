<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Way back in when the authenticator app is lost: turns two-factor login off for one user.
 */
#[AsCommand(name: 'app:user:reset-two-factor', description: 'Turn off two-factor login for a user (lost authenticator app)')]
final class ResetTwoFactorCommand extends Command
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'E-mail address of the user');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $user = $this->users->findOneBy(['email' => (string) $input->getArgument('email')]);
        if ($user === null) {
            $io->error('No user with this e-mail address.');

            return Command::FAILURE;
        }

        $user->setTotpSecret(null);
        $this->entityManager->flush();
        $io->success(sprintf('Two-factor login is now off for %s. They can set it up again under /admin/two-factor.', $user->getEmail()));

        return Command::SUCCESS;
    }
}
