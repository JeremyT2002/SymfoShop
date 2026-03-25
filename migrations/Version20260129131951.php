<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260129131951 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create Payment and ProcessedWebhookEvent entities for Stripe integration';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $isPostgres = $platform instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform;
        $isSqlite = $platform instanceof \Doctrine\DBAL\Platforms\SqlitePlatform;
        
        // `order` is a reserved keyword; identifier quoting differs by platform.
        // PostgreSQL uses double quotes, MySQL/MariaDB needs backticks.
        $orderTable = $isPostgres ? '"order"' : ($isSqlite ? '"order"' : '`order`');

        // Previous runs may have created a partial schema before failing.
        // Allow re-running by dropping the involved tables first.
        $this->addSql('DROP TABLE IF EXISTS processed_webhook_event');
        $this->addSql('DROP TABLE IF EXISTS payment');
        
        $idType = $isPostgres ? 'SERIAL' : 'INTEGER';
        $timestampType = $isPostgres ? 'TIMESTAMP(0) WITHOUT TIME ZONE' : 'DATETIME';
        
        // Create payment table
        if ($isSqlite) {
            $this->addSql("CREATE TABLE payment (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                order_id INTEGER NOT NULL,
                provider VARCHAR(50) NOT NULL,
                payment_intent_id VARCHAR(255) NOT NULL,
                status VARCHAR(50) NOT NULL,
                amount INTEGER NOT NULL,
                currency VARCHAR(3) NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME DEFAULT NULL,
                UNIQUE (payment_intent_id),
                CONSTRAINT FK_payment_order FOREIGN KEY (order_id) REFERENCES $orderTable (id) ON DELETE CASCADE
            )");
        } else {
            $this->addSql("CREATE TABLE payment (
                id {$idType} NOT NULL,
                order_id INT NOT NULL,
                provider VARCHAR(50) NOT NULL,
                payment_intent_id VARCHAR(255) NOT NULL,
                status VARCHAR(50) NOT NULL,
                amount INT NOT NULL,
                currency VARCHAR(3) NOT NULL,
                created_at {$timestampType} NOT NULL,
                updated_at {$timestampType} DEFAULT NULL,
                UNIQUE (payment_intent_id),
                PRIMARY KEY(id)
            )");
            $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_payment_order FOREIGN KEY (order_id) REFERENCES '.$orderTable.' (id) ON DELETE CASCADE');
        }
        $this->addSql('CREATE INDEX idx_payment_order ON payment (order_id)');
        $this->addSql('CREATE INDEX idx_payment_intent_id ON payment (payment_intent_id)');
        $this->addSql('CREATE INDEX idx_payment_status ON payment (status)');

        // Create processed_webhook_event table
        if ($isSqlite) {
            $this->addSql("CREATE TABLE processed_webhook_event (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                event_id VARCHAR(255) NOT NULL,
                processed_at DATETIME NOT NULL,
                UNIQUE (event_id)
            )");
        } else {
            $this->addSql("CREATE TABLE processed_webhook_event (
                id {$idType} NOT NULL,
                event_id VARCHAR(255) NOT NULL,
                processed_at {$timestampType} NOT NULL,
                UNIQUE (event_id),
                PRIMARY KEY(id)
            )");
        }
        $this->addSql('CREATE INDEX idx_webhook_event_id ON processed_webhook_event (event_id)');
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $isSqlite = $platform instanceof \Doctrine\DBAL\Platforms\SqlitePlatform;
        
        if (!$isSqlite) {
            $this->addSql('ALTER TABLE payment DROP CONSTRAINT FK_payment_order');
        }
        $this->addSql('DROP TABLE payment');
        $this->addSql('DROP TABLE processed_webhook_event');
    }
}
