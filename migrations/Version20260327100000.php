<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Version20260324180000 mistakenly dropped order.locale when present and never re-added it.
 */
final class Version20260327100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ensure order.locale column exists (repair broken Version20260324180000).';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $isPostgres = $platform instanceof PostgreSQLPlatform;
        $isSqlite = $platform instanceof SqlitePlatform;

        if ($isSqlite) {
            $columns = $this->connection->fetchAllAssociative('PRAGMA table_info("order")');
            foreach ($columns as $col) {
                if (($col['name'] ?? '') === 'locale') {
                    return;
                }
            }
            $this->addSql('ALTER TABLE "order" ADD COLUMN locale VARCHAR(10) NOT NULL DEFAULT \'en\'');

            return;
        }

        if ($isPostgres) {
            $localeColumnCount = (int) $this->connection->fetchOne(
                "SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = current_schema()
                   AND table_name = 'order'
                   AND column_name = 'locale'"
            );
        } else {
            $localeColumnCount = (int) $this->connection->fetchOne(
                "SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name = 'order'
                   AND column_name = 'locale'"
            );
        }

        if ($localeColumnCount > 0) {
            return;
        }

        $orderTable = $isPostgres ? '"order"' : '`order`';
        $this->addSql(sprintf(
            'ALTER TABLE %s ADD COLUMN locale VARCHAR(10) NOT NULL DEFAULT \'en\'',
            $orderTable
        ));
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
