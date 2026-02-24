<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260129132950 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create AuditLog table and add shipment tracking fields to Order';
    }

    public function up(Schema $schema): void
    {
        // Create audit_log table
        $this->addSql('CREATE TABLE audit_log (
            id SERIAL NOT NULL,
            entity_type VARCHAR(100) NOT NULL,
            entity_id INT DEFAULT NULL,
            action VARCHAR(50) NOT NULL,
            old_value TEXT DEFAULT NULL,
            new_value TEXT DEFAULT NULL,
            changed_field VARCHAR(255) DEFAULT NULL,
            user_identifier VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX idx_audit_log_entity ON audit_log (entity_type, entity_id)');
        $this->addSql('CREATE INDEX idx_audit_log_action ON audit_log (action)');
        $this->addSql('CREATE INDEX idx_audit_log_created ON audit_log (created_at)');

        // Add shipment tracking fields to order table
        $this->addSql('ALTER TABLE "order" ADD tracking_number VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "order" ADD carrier VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE "order" ADD shipped_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `order` DROP tracking_number');
        $this->addSql('ALTER TABLE `order` DROP carrier');
        $this->addSql('ALTER TABLE `order` DROP shipped_at');
        $this->addSql('DROP TABLE audit_log');
    }
}
