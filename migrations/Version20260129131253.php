<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260129131253 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create Customer, Order, and OrderItem entities for checkout system';
    }

    public function up(Schema $schema): void
    {
        // Create customer table
        $this->addSql('CREATE TABLE customer (
            id INT AUTO_INCREMENT NOT NULL,
            email VARCHAR(255) NOT NULL,
            password VARCHAR(255) DEFAULT NULL,
            UNIQUE INDEX UNIQ_customer_email (email),
            INDEX idx_customer_email (email),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Create order table
        $this->addSql('CREATE TABLE `order` (
            id INT AUTO_INCREMENT NOT NULL,
            order_number VARCHAR(50) NOT NULL,
            email VARCHAR(255) NOT NULL,
            currency VARCHAR(3) NOT NULL,
            status VARCHAR(50) NOT NULL,
            subtotal INT NOT NULL,
            tax_total INT NOT NULL,
            grand_total INT NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_order_order_number (order_number),
            INDEX idx_order_order_number (order_number),
            INDEX idx_order_email (email),
            INDEX idx_order_status (status),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Create order_item table
        $this->addSql('CREATE TABLE order_item (
            id INT AUTO_INCREMENT NOT NULL,
            order_id INT NOT NULL,
            sku VARCHAR(255) NOT NULL,
            name_snapshot VARCHAR(500) NOT NULL,
            quantity INT NOT NULL,
            unit_price_amount INT NOT NULL,
            tax_rate NUMERIC(5, 4) NOT NULL,
            total_amount INT NOT NULL,
            INDEX idx_order_item_order (order_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_order_item_order FOREIGN KEY (order_id) REFERENCES `order` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY FK_order_item_order');
        $this->addSql('DROP TABLE customer');
        $this->addSql('DROP TABLE `order`');
        $this->addSql('DROP TABLE order_item');
    }
}
