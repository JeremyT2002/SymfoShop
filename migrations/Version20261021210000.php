<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Per-locale translations for products and categories.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product ADD COLUMN translations TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE category ADD COLUMN translations TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP COLUMN translations');
        $this->addSql('ALTER TABLE category DROP COLUMN translations');
    }
}
