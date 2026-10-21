<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Gross pricing: flag whether an order\'s amounts include VAT (existing orders: no).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `order` ADD COLUMN prices_include_tax BOOLEAN NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `order` DROP COLUMN prices_include_tax');
    }
}
