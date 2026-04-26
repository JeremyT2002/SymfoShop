<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260330114000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix DB column name for Product/Category seoNoIndex (seo_no_index).';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $isPostgres = $platform instanceof PostgreSQLPlatform;
        $isSqlite = $platform instanceof SqlitePlatform;

        if ($isSqlite) {
            $this->addSql('ALTER TABLE product ADD COLUMN seo_no_index INTEGER NOT NULL DEFAULT 0');
            $this->addSql('ALTER TABLE category ADD COLUMN seo_no_index INTEGER NOT NULL DEFAULT 0');

            return;
        }

        if ($isPostgres) {
            $this->addSql('ALTER TABLE product ADD COLUMN seo_no_index BOOLEAN NOT NULL DEFAULT FALSE');
            $this->addSql('ALTER TABLE category ADD COLUMN seo_no_index BOOLEAN NOT NULL DEFAULT FALSE');

            return;
        }

        // MySQL / MariaDB
        $this->addSql('ALTER TABLE `product` ADD COLUMN seo_no_index TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE `category` ADD COLUMN seo_no_index TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $isPostgres = $platform instanceof PostgreSQLPlatform;
        $isSqlite = $platform instanceof SqlitePlatform;

        if ($isSqlite) {
            $this->addSql('ALTER TABLE product DROP COLUMN seo_no_index');
            $this->addSql('ALTER TABLE category DROP COLUMN seo_no_index');

            return;
        }

        if ($isPostgres) {
            $this->addSql('ALTER TABLE product DROP COLUMN seo_no_index');
            $this->addSql('ALTER TABLE category DROP COLUMN seo_no_index');

            return;
        }

        $this->addSql('ALTER TABLE `product` DROP COLUMN seo_no_index');
        $this->addSql('ALTER TABLE `category` DROP COLUMN seo_no_index');
    }
}

