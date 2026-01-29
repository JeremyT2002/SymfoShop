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
            id INT AUTO_INCREMENT NOT NULL,
            order_id INT NOT NULL,
            invoice_number VARCHAR(50) NOT NULL,
            pdf_path VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            sent_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_invoice_invoice_number (invoice_number),
            INDEX idx_invoice_order (order_id),
            INDEX idx_invoice_number (invoice_number),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Add foreign key
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_invoice_order FOREIGN KEY (order_id) REFERENCES `order` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_invoice_order');
        $this->addSql('DROP TABLE invoice');
    }
}
