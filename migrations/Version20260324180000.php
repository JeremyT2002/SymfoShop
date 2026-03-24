<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260324180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add order.locale for checkout locale; create return_request for return submissions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "order" ADD COLUMN locale VARCHAR(10) NOT NULL DEFAULT \'en\'');
        $this->addSql('CREATE TABLE return_request (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, order_number VARCHAR(50) NOT NULL, email VARCHAR(255) NOT NULL, reason CLOB NOT NULL, status VARCHAR(20) DEFAULT \'pending\' NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        )');
        $this->addSql('CREATE INDEX idx_return_request_created_at ON return_request (created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE return_request');
        $this->addSql('ALTER TABLE "order" DROP COLUMN locale');
    }
}
