<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store applied coupon code and discount on orders.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `order` ADD COLUMN coupon_code VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE `order` ADD COLUMN discount_total INTEGER NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `order` DROP COLUMN discount_total');
        $this->addSql('ALTER TABLE `order` DROP COLUMN coupon_code');
    }
}
