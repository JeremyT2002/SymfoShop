<?php

namespace App\Command;

use App\Theme\ThemeConfigService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:theme:init',
    description: 'Create and publish default theme if none exists (single-tenant, shop=null)',
)]
class ThemeInitCommand extends Command
{
    public function __construct(
        private readonly ThemeConfigService $themeConfig,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $theme = $this->themeConfig->getOrCreateDraftTheme(null);
        if ($theme->getStatus() === 'published') {
            $io->success('Default theme already published.');
            return Command::SUCCESS;
        }
        $this->themeConfig->publish($theme, null);
        $io->success('Default theme created and published.');
        return Command::SUCCESS;
    }
}
