<?php

declare(strict_types=1);

namespace App\Command;

use App\Demo\DemoAccounts;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Keeps the demo accounts usable on a public demo instance (run it from cron, e.g. every few minutes):
 * creates missing ones and resets roles, active/verified flags and two-factor login.
 */
#[AsCommand(name: 'app:demo:accounts', description: 'Create or repair the one-click demo accounts (DEMO_MODE only)')]
final class DemoAccountsCommand extends Command
{
    public function __construct(
        private readonly DemoAccounts $demoAccounts,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->demoAccounts->isEnabled()) {
            $io->error('DEMO_MODE is off – demo accounts are only managed on demo instances.');

            return Command::FAILURE;
        }

        foreach (array_keys($this->demoAccounts->all()) as $key) {
            $user = $this->demoAccounts->ensure($key);
            $io->writeln(sprintf('  %-10s %s', $key, $user->getEmail()));
        }
        $io->success('Demo accounts are ready.');

        return Command::SUCCESS;
    }
}
