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
        // Create invoice table
        $this->addSql('CREATE TABLE invoice (
            id SERIAL NOT NULL,
            order_id INT NOT NULL,
            invoice_number VARCHAR(50) NOT NULL,
            pdf_path VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            UNIQUE (invoice_number),
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX idx_invoice_order ON invoice (order_id)');
        $this->addSql('CREATE INDEX idx_invoice_number ON invoice (invoice_number)');

        // Add foreign key
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_invoice_order FOREIGN KEY (order_id) REFERENCES "order" (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_invoice_order');
        $this->addSql('DROP TABLE invoice');
    }
}
