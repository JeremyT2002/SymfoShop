<?php

namespace App\Command;

use App\Message\SendAbandonedCartReminderMessage;
use App\Repository\CartRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(name: 'app:cart:remind-abandoned', description: 'Dispatch reminders for abandoned carts.')]
class RemindAbandonedCartsCommand extends Command
{
    public function __construct(
        private readonly CartRepository $cartRepository,
        private readonly MessageBusInterface $messageBus
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $olderThan = (new \DateTimeImmutable())->modify('-24 hours');
        $newerThan = (new \DateTimeImmutable())->modify('-7 days');
        $carts = $this->cartRepository->findAbandonedCarts($olderThan, $newerThan);

        foreach ($carts as $cart) {
            if ($cart->getId() !== null) {
                $this->messageBus->dispatch(new SendAbandonedCartReminderMessage($cart->getId()));
            }
        }

        $io->success(sprintf('Dispatched %d abandoned-cart reminders.', count($carts)));
        return Command::SUCCESS;
    }
}

