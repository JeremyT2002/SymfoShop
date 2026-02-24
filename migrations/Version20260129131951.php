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
            id SERIAL NOT NULL,
            order_id INT NOT NULL,
            provider VARCHAR(50) NOT NULL,
            payment_intent_id VARCHAR(255) NOT NULL,
            status VARCHAR(50) NOT NULL,
            amount INT NOT NULL,
            currency VARCHAR(3) NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            UNIQUE (payment_intent_id),
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX idx_payment_order ON payment (order_id)');
        $this->addSql('CREATE INDEX idx_payment_intent_id ON payment (payment_intent_id)');
        $this->addSql('CREATE INDEX idx_payment_status ON payment (status)');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_payment_order FOREIGN KEY (order_id) REFERENCES "order" (id) ON DELETE CASCADE');

        // Create processed_webhook_event table
        $this->addSql('CREATE TABLE processed_webhook_event (
            id SERIAL NOT NULL,
            event_id VARCHAR(255) NOT NULL,
            processed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            UNIQUE (event_id),
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX idx_webhook_event_id ON processed_webhook_event (event_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_payment_order');
        $this->addSql('DROP TABLE payment');
        $this->addSql('DROP TABLE processed_webhook_event');
    }
}
