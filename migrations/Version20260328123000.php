<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260328123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add category and related_order_number to support conversations.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE support_conversation ADD category VARCHAR(30) NOT NULL DEFAULT \'other\'');
        $this->addSql('ALTER TABLE support_conversation ADD related_order_number VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if ($platform->getName() === 'sqlite') {
            $this->write('SQLite: DROP COLUMN skipped for support_conversation.category/related_order_number');
            return;
        }

        $this->addSql('ALTER TABLE support_conversation DROP COLUMN category');
        $this->addSql('ALTER TABLE support_conversation DROP COLUMN related_order_number');
    }
}

