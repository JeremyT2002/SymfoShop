<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260325090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add status column to processed_webhook_event for Stripe webhook claim/completion.';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $isSqlite = $platform instanceof \Doctrine\DBAL\Platforms\SqlitePlatform;

        if ($isSqlite) {
            $this->addSql("ALTER TABLE processed_webhook_event ADD COLUMN status VARCHAR(32) NOT NULL DEFAULT 'completed'");
        } else {
            $this->addSql("ALTER TABLE processed_webhook_event ADD COLUMN status VARCHAR(32) NOT NULL DEFAULT 'completed'");
        }
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if ($platform instanceof \Doctrine\DBAL\Platforms\SqlitePlatform) {
            $this->write('SQLite: skipped DROP COLUMN status (rebuild DB or migrate forward).');

            return;
        }

        $this->addSql('ALTER TABLE processed_webhook_event DROP COLUMN status');
    }
}
