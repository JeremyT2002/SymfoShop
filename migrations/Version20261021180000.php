<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'E-mail verification flag on users (existing accounts count as verified).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD COLUMN is_verified BOOLEAN NOT NULL DEFAULT 0');
        $this->addSql('UPDATE user SET is_verified = 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP COLUMN is_verified');
    }
}
