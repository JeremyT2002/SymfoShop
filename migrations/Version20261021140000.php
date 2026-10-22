<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store customer name, phone and shipping address on orders.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `order` ADD COLUMN first_name VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE `order` ADD COLUMN last_name VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE `order` ADD COLUMN phone VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE `order` ADD COLUMN shipping_street VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE `order` ADD COLUMN shipping_postal_code VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE `order` ADD COLUMN shipping_city VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE `order` ADD COLUMN shipping_state VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE `order` ADD COLUMN shipping_country VARCHAR(2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `order` DROP COLUMN shipping_country');
        $this->addSql('ALTER TABLE `order` DROP COLUMN shipping_state');
        $this->addSql('ALTER TABLE `order` DROP COLUMN shipping_city');
        $this->addSql('ALTER TABLE `order` DROP COLUMN shipping_postal_code');
        $this->addSql('ALTER TABLE `order` DROP COLUMN shipping_street');
        $this->addSql('ALTER TABLE `order` DROP COLUMN phone');
        $this->addSql('ALTER TABLE `order` DROP COLUMN last_name');
        $this->addSql('ALTER TABLE `order` DROP COLUMN first_name');
    }
}
