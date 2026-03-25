<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260129132753 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create Invoice table for invoice management';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $isPostgres = $platform instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform;
        $isSqlite = $platform instanceof \Doctrine\DBAL\Platforms\SqlitePlatform;

        // `order` is a reserved keyword; identifier quoting differs by platform.
        $orderTable = $isPostgres ? '"order"' : ($isSqlite ? '"order"' : '`order`');

        // Allow re-running after partial schema creation.
        $this->addSql('DROP TABLE IF EXISTS invoice');
        
        $idType = $isPostgres ? 'SERIAL' : 'INTEGER';
        $timestampType = $isPostgres ? 'TIMESTAMP(0) WITHOUT TIME ZONE' : 'DATETIME';
        
        // Create invoice table
        if ($isSqlite) {
            $this->addSql("CREATE TABLE invoice (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                order_id INTEGER NOT NULL,
                invoice_number VARCHAR(50) NOT NULL,
                pdf_path VARCHAR(255) DEFAULT NULL,
                created_at DATETIME NOT NULL,
                sent_at DATETIME DEFAULT NULL,
                UNIQUE (invoice_number),
                CONSTRAINT FK_invoice_order FOREIGN KEY (order_id) REFERENCES $orderTable (id) ON DELETE CASCADE
            )");
        } else {
            $this->addSql("CREATE TABLE invoice (
                id {$idType} NOT NULL,
                order_id INT NOT NULL,
                invoice_number VARCHAR(50) NOT NULL,
                pdf_path VARCHAR(255) DEFAULT NULL,
                created_at {$timestampType} NOT NULL,
                sent_at {$timestampType} DEFAULT NULL,
                UNIQUE (invoice_number),
                PRIMARY KEY(id)
            )");
            $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_invoice_order FOREIGN KEY (order_id) REFERENCES '.$orderTable.' (id) ON DELETE CASCADE');
        }
        $this->addSql('CREATE INDEX idx_invoice_order ON invoice (order_id)');
        $this->addSql('CREATE INDEX idx_invoice_number ON invoice (invoice_number)');
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $isSqlite = $platform instanceof \Doctrine\DBAL\Platforms\SqlitePlatform;
        
        if (!$isSqlite) {
            $this->addSql('ALTER TABLE invoice DROP CONSTRAINT FK_invoice_order');
        }
        $this->addSql('DROP TABLE invoice');
    }
}
