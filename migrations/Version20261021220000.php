<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Refunds with credit note numbers.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE refund (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, order_id INTEGER NOT NULL, return_request_id INTEGER DEFAULT NULL, credit_note_number VARCHAR(50) NOT NULL, amount INTEGER NOT NULL, tax_amount INTEGER NOT NULL, currency VARCHAR(3) NOT NULL, reason TEXT NOT NULL, provider VARCHAR(50) NOT NULL, provider_reference VARCHAR(255) DEFAULT NULL, status VARCHAR(20) NOT NULL, restocked BOOLEAN DEFAULT 0 NOT NULL, created_by VARCHAR(180) DEFAULT NULL, created_at DATETIME NOT NULL, CONSTRAINT FK_refund_order FOREIGN KEY (order_id) REFERENCES `order` (id) ON DELETE CASCADE, CONSTRAINT FK_refund_return_request FOREIGN KEY (return_request_id) REFERENCES return_request (id) ON DELETE SET NULL)');
        $this->addSql('CREATE UNIQUE INDEX uniq_refund_credit_note ON refund (credit_note_number)');
        $this->addSql('CREATE INDEX idx_refund_order ON refund (order_id)');
        $this->addSql('CREATE INDEX idx_refund_return_request ON refund (return_request_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE refund');
    }
}
