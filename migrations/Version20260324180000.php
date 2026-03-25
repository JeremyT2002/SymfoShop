<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260324180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add order.locale for checkout locale; create return_request for return submissions.';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $isSqlite = $platform instanceof \Doctrine\DBAL\Platforms\SqlitePlatform;
        $orderTable = $platform instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform ? '"order"' : '`order`';

        // Re-run safety: previous attempts may have already added `order.locale`
        // before failing on the subsequent CREATE TABLE.
        $this->addSql('DROP TABLE IF EXISTS return_request');
        // Drop locale only if it exists (rerunnable migration).
        if (!$isSqlite) {
            $localeColumnCount = (int) $this->connection->fetchOne(
                "SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name = 'order'
                   AND column_name = 'locale'"
            );

            if ($localeColumnCount > 0) {
                $this->addSql('ALTER TABLE '.$orderTable.' DROP COLUMN locale');
            }
        }

        // Create return_request table (clean SQL without Doctrine inline hints).
        $this->addSql('CREATE TABLE return_request (
            id INTEGER NOT NULL AUTO_INCREMENT,
            order_number VARCHAR(50) NOT NULL,
            email VARCHAR(255) NOT NULL,
            reason TEXT NOT NULL,
            status VARCHAR(20) DEFAULT \'pending\' NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX idx_return_request_created_at ON return_request (created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE return_request');
        $platform = $this->connection->getDatabasePlatform();
        $orderTable = $platform instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform ? '"order"' : '`order`';

        $this->addSql('ALTER TABLE '.$orderTable.' DROP COLUMN locale');
    }
}
