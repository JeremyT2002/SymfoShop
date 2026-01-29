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
        // Create payment table
        $this->addSql('CREATE TABLE payment (
            id INT AUTO_INCREMENT NOT NULL,
            order_id INT NOT NULL,
            provider VARCHAR(50) NOT NULL,
            payment_intent_id VARCHAR(255) NOT NULL,
            status VARCHAR(50) NOT NULL,
            amount INT NOT NULL,
            currency VARCHAR(3) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_payment_payment_intent_id (payment_intent_id),
            INDEX idx_payment_order (order_id),
            INDEX idx_payment_intent_id (payment_intent_id),
            INDEX idx_payment_status (status),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_payment_order FOREIGN KEY (order_id) REFERENCES `order` (id) ON DELETE CASCADE');

        // Create processed_webhook_event table
        $this->addSql('CREATE TABLE processed_webhook_event (
            id INT AUTO_INCREMENT NOT NULL,
            event_id VARCHAR(255) NOT NULL,
            processed_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_processed_webhook_event_event_id (event_id),
            INDEX idx_webhook_event_id (event_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_payment_order');
        $this->addSql('DROP TABLE payment');
        $this->addSql('DROP TABLE processed_webhook_event');
    }
}
