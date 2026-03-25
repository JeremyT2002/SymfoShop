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
final class Version20260129132404 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create StockItem and OrderReservation tables for inventory management';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $isPostgres = $platform instanceof PostgreSQLPlatform;
        $isSqlite = $platform instanceof SqlitePlatform;

        // `order` is reserved; escape identifiers per platform.
        $orderTable = $isPostgres ? '"order"' : ($isSqlite ? '"order"' : '`order`');

        // Previous runs may have created partial schema before failing (e.g. FK quoting).
        // Make the migration re-runnable on MariaDB/MySQL.
        $this->addSql('DROP TABLE IF EXISTS order_reservation');
        $this->addSql('DROP TABLE IF EXISTS stock_item');
        
        $idType = $isPostgres ? 'SERIAL' : 'INTEGER';
        $timestampType = $isPostgres ? 'TIMESTAMP(0) WITHOUT TIME ZONE' : 'DATETIME';
        
        // Create stock_item table
        if ($isSqlite) {
            $this->addSql("CREATE TABLE stock_item (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                variant_id INTEGER NOT NULL,
                on_hand INTEGER NOT NULL,
                reserved INTEGER NOT NULL,
                version INTEGER NOT NULL DEFAULT 0,
                UNIQUE (variant_id),
                CONSTRAINT FK_stock_item_variant FOREIGN KEY (variant_id) REFERENCES product_variant (id) ON DELETE CASCADE
            )");
        } else {
            $this->addSql("CREATE TABLE stock_item (
                id {$idType} NOT NULL,
                variant_id INT NOT NULL,
                on_hand INT NOT NULL,
                reserved INT NOT NULL,
                version INT NOT NULL DEFAULT 0,
                UNIQUE (variant_id),
                PRIMARY KEY(id)
            )");
            $this->addSql('ALTER TABLE stock_item ADD CONSTRAINT FK_stock_item_variant FOREIGN KEY (variant_id) REFERENCES product_variant (id) ON DELETE CASCADE');
        }
        $this->addSql('CREATE INDEX idx_stock_item_variant ON stock_item (variant_id)');

        // Create order_reservation table
        if ($isSqlite) {
            $this->addSql("CREATE TABLE order_reservation (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                order_id INTEGER NOT NULL,
                variant_id INTEGER NOT NULL,
                quantity INTEGER NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                CONSTRAINT FK_order_reservation_order FOREIGN KEY (order_id) REFERENCES $orderTable (id) ON DELETE CASCADE,
                CONSTRAINT FK_order_reservation_variant FOREIGN KEY (variant_id) REFERENCES product_variant (id) ON DELETE CASCADE
            )");
        } else {
            $this->addSql("CREATE TABLE order_reservation (
                id {$idType} NOT NULL,
                order_id INT NOT NULL,
                variant_id INT NOT NULL,
                quantity INT NOT NULL,
                expires_at {$timestampType} NOT NULL,
                created_at {$timestampType} NOT NULL,
                PRIMARY KEY(id)
            )");
            $this->addSql('ALTER TABLE order_reservation ADD CONSTRAINT FK_order_reservation_order FOREIGN KEY (order_id) REFERENCES '.$orderTable.' (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE order_reservation ADD CONSTRAINT FK_order_reservation_variant FOREIGN KEY (variant_id) REFERENCES product_variant (id) ON DELETE CASCADE');
        }
        $this->addSql('CREATE INDEX idx_order_reservation_order ON order_reservation (order_id)');
        $this->addSql('CREATE INDEX idx_order_reservation_variant ON order_reservation (variant_id)');
        $this->addSql('CREATE INDEX idx_order_reservation_expires ON order_reservation (expires_at)');
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $isSqlite = $platform instanceof SqlitePlatform;
        
        if (!$isSqlite) {
            $this->addSql('ALTER TABLE stock_item DROP CONSTRAINT FK_stock_item_variant');
            $this->addSql('ALTER TABLE order_reservation DROP CONSTRAINT FK_order_reservation_order');
            $this->addSql('ALTER TABLE order_reservation DROP CONSTRAINT FK_order_reservation_variant');
        }
        $this->addSql('DROP TABLE stock_item');
        $this->addSql('DROP TABLE order_reservation');
    }
}
