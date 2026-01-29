<?php

namespace App\Command;

use App\Service\Inventory\InventoryService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:inventory:cleanup-reservations',
    description: 'Release expired inventory reservations'
)]
class CleanupExpiredReservationsCommand extends Command
{
    public function __construct(
        private readonly InventoryService $inventoryService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->info('Cleaning up expired reservations...');

        $count = $this->inventoryService->releaseExpiredReservations();

        if ($count > 0) {
            $io->success(sprintf('Released %d expired reservation(s)', $count));
        } else {
            $io->info('No expired reservations found');
        }

        return Command::SUCCESS;
    }
}

