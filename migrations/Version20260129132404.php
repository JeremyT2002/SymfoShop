<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260129132404 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create StockItem and OrderReservation tables for inventory management';
    }

    public function up(Schema $schema): void
    {
        // Create stock_item table
        $this->addSql('CREATE TABLE stock_item (
            id INT AUTO_INCREMENT NOT NULL,
            variant_id INT NOT NULL,
            on_hand INT NOT NULL,
            reserved INT NOT NULL,
            version INT NOT NULL,
            UNIQUE INDEX UNIQ_stock_item_variant (variant_id),
            INDEX idx_stock_item_variant (variant_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Create order_reservation table
        $this->addSql('CREATE TABLE order_reservation (
            id INT AUTO_INCREMENT NOT NULL,
            order_id INT NOT NULL,
            variant_id INT NOT NULL,
            quantity INT NOT NULL,
            expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_order_reservation_order (order_id),
            INDEX IDX_order_reservation_variant (variant_id),
            INDEX idx_order_reservation_order (order_id),
            INDEX idx_order_reservation_expires (expires_at),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Add foreign keys
        $this->addSql('ALTER TABLE stock_item ADD CONSTRAINT FK_stock_item_variant FOREIGN KEY (variant_id) REFERENCES product_variant (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE order_reservation ADD CONSTRAINT FK_order_reservation_order FOREIGN KEY (order_id) REFERENCES `order` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE order_reservation ADD CONSTRAINT FK_order_reservation_variant FOREIGN KEY (variant_id) REFERENCES product_variant (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE stock_item DROP FOREIGN KEY FK_stock_item_variant');
        $this->addSql('ALTER TABLE order_reservation DROP FOREIGN KEY FK_order_reservation_order');
        $this->addSql('ALTER TABLE order_reservation DROP FOREIGN KEY FK_order_reservation_variant');
        $this->addSql('DROP TABLE stock_item');
        $this->addSql('DROP TABLE order_reservation');
    }
}
