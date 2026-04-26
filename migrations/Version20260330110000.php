<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260330110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add SEO title/description/noindex fields to products and categories.';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $isPostgres = $platform instanceof PostgreSQLPlatform;
        $isSqlite = $platform instanceof SqlitePlatform;

        if ($isSqlite) {
            $this->addSql('ALTER TABLE product ADD COLUMN seo_title VARCHAR(255) DEFAULT NULL');
            $this->addSql('ALTER TABLE product ADD COLUMN seo_description TEXT DEFAULT NULL');
            $this->addSql('ALTER TABLE product ADD COLUMN seo_noindex INTEGER NOT NULL DEFAULT 0');

            $this->addSql('ALTER TABLE category ADD COLUMN seo_title VARCHAR(255) DEFAULT NULL');
            $this->addSql('ALTER TABLE category ADD COLUMN seo_description TEXT DEFAULT NULL');
            $this->addSql('ALTER TABLE category ADD COLUMN seo_noindex INTEGER NOT NULL DEFAULT 0');

            return;
        }

        if ($isPostgres) {
            $this->addSql('ALTER TABLE product ADD COLUMN seo_title VARCHAR(255) NULL');
            $this->addSql('ALTER TABLE product ADD COLUMN seo_description TEXT NULL');
            $this->addSql('ALTER TABLE product ADD COLUMN seo_noindex BOOLEAN NOT NULL DEFAULT FALSE');

            $this->addSql('ALTER TABLE category ADD COLUMN seo_title VARCHAR(255) NULL');
            $this->addSql('ALTER TABLE category ADD COLUMN seo_description TEXT NULL');
            $this->addSql('ALTER TABLE category ADD COLUMN seo_noindex BOOLEAN NOT NULL DEFAULT FALSE');

            return;
        }

        // MySQL / MariaDB
        $this->addSql('ALTER TABLE `product` ADD COLUMN seo_title VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE `product` ADD COLUMN seo_description MEDIUMTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE `product` ADD COLUMN seo_noindex TINYINT(1) NOT NULL DEFAULT 0');

        $this->addSql('ALTER TABLE `category` ADD COLUMN seo_title VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE `category` ADD COLUMN seo_description MEDIUMTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE `category` ADD COLUMN seo_noindex TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $isPostgres = $platform instanceof PostgreSQLPlatform;
        $isSqlite = $platform instanceof SqlitePlatform;

        // DROP COLUMN support is database-dependent, but down() is mainly for rollbacks in non-SQLite setups.
        if ($isSqlite) {
            $this->addSql('ALTER TABLE product DROP COLUMN seo_title');
            $this->addSql('ALTER TABLE product DROP COLUMN seo_description');
            $this->addSql('ALTER TABLE product DROP COLUMN seo_noindex');

            $this->addSql('ALTER TABLE category DROP COLUMN seo_title');
            $this->addSql('ALTER TABLE category DROP COLUMN seo_description');
            $this->addSql('ALTER TABLE category DROP COLUMN seo_noindex');

            return;
        }

        if ($isPostgres) {
            $this->addSql('ALTER TABLE product DROP COLUMN seo_title');
            $this->addSql('ALTER TABLE product DROP COLUMN seo_description');
            $this->addSql('ALTER TABLE product DROP COLUMN seo_noindex');

            $this->addSql('ALTER TABLE category DROP COLUMN seo_title');
            $this->addSql('ALTER TABLE category DROP COLUMN seo_description');
            $this->addSql('ALTER TABLE category DROP COLUMN seo_noindex');

            return;
        }

        // MySQL / MariaDB
        $this->addSql('ALTER TABLE `product` DROP COLUMN seo_title');
        $this->addSql('ALTER TABLE `product` DROP COLUMN seo_description');
        $this->addSql('ALTER TABLE `product` DROP COLUMN seo_noindex');

        $this->addSql('ALTER TABLE `category` DROP COLUMN seo_title');
        $this->addSql('ALTER TABLE `category` DROP COLUMN seo_description');
        $this->addSql('ALTER TABLE `category` DROP COLUMN seo_noindex');
    }
}

