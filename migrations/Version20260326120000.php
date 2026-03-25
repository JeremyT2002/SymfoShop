<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260326120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add shipping_method table (seed standard/express) and order shipping columns.';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $isPostgres = $platform instanceof PostgreSQLPlatform;
        $isSqlite = $platform instanceof SqlitePlatform;
        $orderTable = $isPostgres || $isSqlite ? '"order"' : '`order`';

        $this->addSql('DROP TABLE IF EXISTS shipping_method');
        // Remove Doctrine inline type hints like `--(DC2Type:...)` because `--` comments
        // require whitespace after `--` in MariaDB/MySQL.
        if ($isSqlite) {
            $this->addSql('CREATE TABLE shipping_method (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                code VARCHAR(50) NOT NULL,
                name VARCHAR(120) NOT NULL,
                amount_cents INTEGER DEFAULT 0 NOT NULL,
                is_active BOOLEAN DEFAULT 1 NOT NULL,
                sort_order INTEGER DEFAULT 0 NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME DEFAULT NULL
            )');
        } elseif ($isPostgres) {
            $this->addSql('CREATE TABLE shipping_method (
                id SERIAL NOT NULL,
                code VARCHAR(50) NOT NULL,
                name VARCHAR(120) NOT NULL,
                amount_cents INTEGER DEFAULT 0 NOT NULL,
                is_active BOOLEAN DEFAULT true NOT NULL,
                sort_order INTEGER DEFAULT 0 NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY(id)
            )');
        } else {
            $this->addSql('CREATE TABLE shipping_method (
                id INTEGER NOT NULL AUTO_INCREMENT,
                code VARCHAR(50) NOT NULL,
                name VARCHAR(120) NOT NULL,
                amount_cents INTEGER DEFAULT 0 NOT NULL,
                is_active BOOLEAN DEFAULT 1 NOT NULL,
                sort_order INTEGER DEFAULT 0 NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME DEFAULT NULL,
                PRIMARY KEY(id)
            )');
        }
        $this->addSql('CREATE UNIQUE INDEX uniq_shipping_method_code ON shipping_method (code)');

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->addSql("INSERT INTO shipping_method (code, name, amount_cents, is_active, sort_order, created_at) VALUES ('standard', 'Standard shipping', 0, 1, 10, '$now')");
        $this->addSql("INSERT INTO shipping_method (code, name, amount_cents, is_active, sort_order, created_at) VALUES ('express', 'Express shipping', 500, 1, 20, '$now')");

        $this->addSql('ALTER TABLE '.$orderTable.' ADD COLUMN shipping_amount INTEGER NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE '.$orderTable.' ADD COLUMN shipping_method_code VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE '.$orderTable.' ADD COLUMN shipping_method_label VARCHAR(120) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $isPostgres = $platform instanceof PostgreSQLPlatform;
        $isSqlite = $platform instanceof SqlitePlatform;
        $orderTable = $isPostgres || $isSqlite ? '"order"' : '`order`';
        if ($isSqlite) {
            $this->write('SQLite: skipped DROP COLUMN on order (shipping_*); rebuild DB or migrate forward.');
        } else {
            $this->addSql('ALTER TABLE '.$orderTable.' DROP COLUMN shipping_amount');
            $this->addSql('ALTER TABLE '.$orderTable.' DROP COLUMN shipping_method_code');
            $this->addSql('ALTER TABLE '.$orderTable.' DROP COLUMN shipping_method_label');
        }

        $this->addSql('DROP TABLE shipping_method');
    }
}
