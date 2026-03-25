<?php

declare(strict_types=1);

namespace DoctrineMigrations;

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
        $orderTable = $platform instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform ? '"order"' : '`order`';

        $this->addSql('DROP TABLE IF EXISTS shipping_method');
        // Remove Doctrine inline type hints like `--(DC2Type:...)` because `--` comments
        // require whitespace after `--` in MariaDB/MySQL.
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
        $orderTable = $platform instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform ? '"order"' : '`order`';
        if ($platform instanceof \Doctrine\DBAL\Platforms\SqlitePlatform) {
            $this->write('SQLite: skipped DROP COLUMN on order (shipping_*); rebuild DB or migrate forward.');
        } else {
            $this->addSql('ALTER TABLE '.$orderTable.' DROP COLUMN shipping_amount');
            $this->addSql('ALTER TABLE '.$orderTable.' DROP COLUMN shipping_method_code');
            $this->addSql('ALTER TABLE '.$orderTable.' DROP COLUMN shipping_method_label');
        }

        $this->addSql('DROP TABLE shipping_method');
    }
}
