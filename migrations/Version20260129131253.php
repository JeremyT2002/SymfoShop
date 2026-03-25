<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260129131253 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create Customer, Order, and OrderItem entities for checkout system';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $isPostgres = $platform instanceof PostgreSQLPlatform;
        $isSqlite = $platform instanceof SqlitePlatform;

        // Reserved keyword: use DBAL’s per-platform quoting (`order` on MySQL/MariaDB, "order" on PostgreSQL/SQLite).
        $orderTable = $platform->quoteSingleIdentifier('order');

        // Previous runs may have created a partial schema before failing on the `order` table.
        // Ensure we can re-run migrations safely for a fresh/empty database.
        $this->addSql('DROP TABLE IF EXISTS order_item');
        $this->addSql('DROP TABLE IF EXISTS '.$orderTable);
        $this->addSql('DROP TABLE IF EXISTS customer');
        
        $idType = $isPostgres ? 'SERIAL' : 'INTEGER';
        $timestampType = $isPostgres ? 'TIMESTAMP(0) WITHOUT TIME ZONE' : 'DATETIME';
        
        // Create customer table
        if ($isSqlite) {
            $this->addSql("CREATE TABLE customer (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                email VARCHAR(255) NOT NULL,
                password VARCHAR(255) DEFAULT NULL,
                UNIQUE (email)
            )");
        } else {
            $this->addSql("CREATE TABLE customer (
                id {$idType} NOT NULL,
                email VARCHAR(255) NOT NULL,
                password VARCHAR(255) DEFAULT NULL,
                UNIQUE (email),
                PRIMARY KEY(id)
            )");
        }
        $this->addSql('CREATE INDEX idx_customer_email ON customer (email)');

        // Create order table
        if ($isSqlite) {
            $this->addSql("CREATE TABLE \"order\" (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                order_number VARCHAR(50) NOT NULL,
                email VARCHAR(255) NOT NULL,
                currency VARCHAR(3) NOT NULL,
                status VARCHAR(50) NOT NULL,
                subtotal INTEGER NOT NULL,
                tax_total INTEGER NOT NULL,
                grand_total INTEGER NOT NULL,
                created_at DATETIME NOT NULL,
                UNIQUE (order_number)
            )");
        } else {
            $this->addSql(
                'CREATE TABLE '.$orderTable.' (
                    id '.$idType.' NOT NULL,
                    order_number VARCHAR(50) NOT NULL,
                    email VARCHAR(255) NOT NULL,
                    currency VARCHAR(3) NOT NULL,
                    status VARCHAR(50) NOT NULL,
                    subtotal INT NOT NULL,
                    tax_total INT NOT NULL,
                    grand_total INT NOT NULL,
                    created_at '.$timestampType.' NOT NULL,
                    UNIQUE (order_number),
                    PRIMARY KEY(id)
                )'
            );
        }
        $this->addSql('CREATE INDEX idx_order_order_number ON '.$orderTable.' (order_number)');
        $this->addSql('CREATE INDEX idx_order_email ON '.$orderTable.' (email)');
        $this->addSql('CREATE INDEX idx_order_status ON '.$orderTable.' (status)');

        // Create order_item table
        if ($isSqlite) {
            $this->addSql('CREATE TABLE order_item (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                order_id INTEGER NOT NULL,
                sku VARCHAR(255) NOT NULL,
                name_snapshot VARCHAR(500) NOT NULL,
                quantity INTEGER NOT NULL,
                unit_price_amount INTEGER NOT NULL,
                tax_rate NUMERIC(5, 4) NOT NULL,
                total_amount INTEGER NOT NULL,
                CONSTRAINT FK_order_item_order FOREIGN KEY (order_id) REFERENCES '.$orderTable.' (id) ON DELETE CASCADE
            )');
        } else {
            $this->addSql("CREATE TABLE order_item (
                id {$idType} NOT NULL,
                order_id INT NOT NULL,
                sku VARCHAR(255) NOT NULL,
                name_snapshot VARCHAR(500) NOT NULL,
                quantity INT NOT NULL,
                unit_price_amount INT NOT NULL,
                tax_rate NUMERIC(5, 4) NOT NULL,
                total_amount INT NOT NULL,
                PRIMARY KEY(id)
            )");
            $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_order_item_order FOREIGN KEY (order_id) REFERENCES '.$orderTable.' (id) ON DELETE CASCADE');
        }
        $this->addSql('CREATE INDEX idx_order_item_order ON order_item (order_id)');
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $isSqlite = $platform instanceof SqlitePlatform;
        $orderTable = $platform->quoteSingleIdentifier('order');

        if (!$isSqlite) {
            $this->addSql('ALTER TABLE order_item DROP CONSTRAINT FK_order_item_order');
        }
        $this->addSql('DROP TABLE IF EXISTS order_item');
        $this->addSql('DROP TABLE IF EXISTS '.$orderTable);
        $this->addSql('DROP TABLE IF EXISTS customer');
    }
}
