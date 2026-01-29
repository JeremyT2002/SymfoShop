<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260129130147 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create Catalog Domain entities: Product, ProductVariant, Category, ProductMedia';
    }

    public function up(Schema $schema): void
    {
        // Create category table
        $this->addSql('CREATE TABLE category (
            id INT AUTO_INCREMENT NOT NULL,
            parent_id INT DEFAULT NULL,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            INDEX idx_category_slug (slug),
            INDEX idx_category_parent (parent_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_category_parent FOREIGN KEY (parent_id) REFERENCES category (id) ON DELETE SET NULL');

        // Create product table
        $this->addSql('CREATE TABLE product (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(255) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            status VARCHAR(50) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            tax_class VARCHAR(100) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_product_slug (slug),
            INDEX idx_product_slug (slug),
            INDEX idx_product_status (status),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Create product_variant table
        $this->addSql('CREATE TABLE product_variant (
            id INT AUTO_INCREMENT NOT NULL,
            product_id INT NOT NULL,
            sku VARCHAR(255) NOT NULL,
            price_amount INT NOT NULL,
            currency VARCHAR(3) DEFAULT \'EUR\' NOT NULL,
            attributes JSON NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_product_variant_sku (sku),
            INDEX idx_product_variant_sku (sku),
            INDEX idx_product_variant_product (product_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE product_variant ADD CONSTRAINT FK_product_variant_product FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');

        // Create product_media table
        $this->addSql('CREATE TABLE product_media (
            id INT AUTO_INCREMENT NOT NULL,
            product_id INT NOT NULL,
            path VARCHAR(500) NOT NULL,
            alt VARCHAR(255) DEFAULT NULL,
            sort INT NOT NULL,
            INDEX idx_product_media_product (product_id),
            INDEX idx_product_media_product_sort (product_id, sort),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE product_media ADD CONSTRAINT FK_product_media_product FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE category DROP FOREIGN KEY FK_category_parent');
        $this->addSql('ALTER TABLE product_variant DROP FOREIGN KEY FK_product_variant_product');
        $this->addSql('ALTER TABLE product_media DROP FOREIGN KEY FK_product_media_product');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE product');
        $this->addSql('DROP TABLE product_variant');
        $this->addSql('DROP TABLE product_media');
    }
}
