<?php

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup:expired-reset-tokens',
    description: 'Clean up expired password reset tokens',
)]
class CleanupExpiredResetTokensCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $now = new \DateTimeImmutable();
        $users = $this->userRepository->createQueryBuilder('u')
            ->where('u.resetToken IS NOT NULL')
            ->andWhere('u.resetTokenExpiresAt < :now')
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();

        $count = 0;
        foreach ($users as $user) {
            $user->setResetToken(null);
            $user->setResetTokenExpiresAt(null);
            $count++;
        }

        if ($count > 0) {
            $this->entityManager->flush();
            $io->success(sprintf('Cleaned up %d expired reset token(s).', $count));
        } else {
            $io->info('No expired reset tokens to clean up.');
        }

        return Command::SUCCESS;
    }
}

