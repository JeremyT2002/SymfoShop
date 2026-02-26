<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
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
        $platform = $this->connection->getDatabasePlatform();
        $isPostgres = $platform instanceof PostgreSQLPlatform;
        $isSqlite = $platform instanceof SqlitePlatform;
        
        // Use platform-appropriate ID type
        $idType = $isPostgres ? 'SERIAL' : 'INTEGER';
        $timestampType = $isPostgres ? 'TIMESTAMP(0) WITHOUT TIME ZONE' : 'DATETIME';
        
        // Create category table with foreign key constraint inline for SQLite
        if ($isSqlite) {
            $this->addSql('CREATE TABLE category (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                parent_id INTEGER DEFAULT NULL,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                CONSTRAINT FK_category_parent FOREIGN KEY (parent_id) REFERENCES category (id) ON DELETE SET NULL
            )');
        } else {
            $this->addSql("CREATE TABLE category (
                id {$idType} NOT NULL,
                parent_id INT DEFAULT NULL,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                PRIMARY KEY(id)
            )");
            $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_category_parent FOREIGN KEY (parent_id) REFERENCES category (id) ON DELETE SET NULL');
        }
        $this->addSql('CREATE INDEX idx_category_slug ON category (slug)');
        $this->addSql('CREATE INDEX idx_category_parent ON category (parent_id)');

        // Create product table
        if ($isSqlite) {
            $this->addSql("CREATE TABLE product (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                name VARCHAR(255) NOT NULL,
                description TEXT DEFAULT NULL,
                status VARCHAR(50) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                tax_class VARCHAR(100) NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            )");
        } else {
            $this->addSql("CREATE TABLE product (
                id {$idType} NOT NULL,
                name VARCHAR(255) NOT NULL,
                description TEXT DEFAULT NULL,
                status VARCHAR(50) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                tax_class VARCHAR(100) NOT NULL,
                created_at {$timestampType} NOT NULL,
                updated_at {$timestampType} NOT NULL,
                PRIMARY KEY(id)
            )");
        }
        $this->addSql('CREATE UNIQUE INDEX UNIQ_product_slug ON product (slug)');
        $this->addSql('CREATE INDEX idx_product_slug ON product (slug)');
        $this->addSql('CREATE INDEX idx_product_status ON product (status)');

        // Create product_variant table
        if ($isSqlite) {
            $this->addSql("CREATE TABLE product_variant (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                product_id INTEGER NOT NULL,
                sku VARCHAR(255) NOT NULL,
                price_amount INTEGER NOT NULL,
                currency VARCHAR(3) DEFAULT 'EUR' NOT NULL,
                attributes TEXT NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                CONSTRAINT FK_product_variant_product FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE
            )");
        } else {
            $this->addSql("CREATE TABLE product_variant (
                id {$idType} NOT NULL,
                product_id INT NOT NULL,
                sku VARCHAR(255) NOT NULL,
                price_amount INT NOT NULL,
                currency VARCHAR(3) DEFAULT 'EUR' NOT NULL,
                attributes JSON NOT NULL,
                created_at {$timestampType} NOT NULL,
                updated_at {$timestampType} NOT NULL,
                PRIMARY KEY(id)
            )");
            $this->addSql('ALTER TABLE product_variant ADD CONSTRAINT FK_product_variant_product FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        }
        $this->addSql('CREATE UNIQUE INDEX UNIQ_product_variant_sku ON product_variant (sku)');
        $this->addSql('CREATE INDEX idx_product_variant_sku ON product_variant (sku)');
        $this->addSql('CREATE INDEX idx_product_variant_product ON product_variant (product_id)');

        // Create product_media table
        if ($isSqlite) {
            $this->addSql("CREATE TABLE product_media (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                product_id INTEGER NOT NULL,
                path VARCHAR(500) NOT NULL,
                alt VARCHAR(255) DEFAULT NULL,
                sort INTEGER NOT NULL,
                CONSTRAINT FK_product_media_product FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE
            )");
        } else {
            $this->addSql("CREATE TABLE product_media (
                id {$idType} NOT NULL,
                product_id INT NOT NULL,
                path VARCHAR(500) NOT NULL,
                alt VARCHAR(255) DEFAULT NULL,
                sort INT NOT NULL,
                PRIMARY KEY(id)
            )");
            $this->addSql('ALTER TABLE product_media ADD CONSTRAINT FK_product_media_product FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        }
        $this->addSql('CREATE INDEX idx_product_media_product ON product_media (product_id)');
        $this->addSql('CREATE INDEX idx_product_media_product_sort ON product_media (product_id, sort)');
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $isSqlite = $platform instanceof SqlitePlatform;
        
        // SQLite doesn't support DROP CONSTRAINT, so just drop tables
        if (!$isSqlite) {
            $this->addSql('ALTER TABLE category DROP CONSTRAINT FK_category_parent');
            $this->addSql('ALTER TABLE product_variant DROP CONSTRAINT FK_product_variant_product');
            $this->addSql('ALTER TABLE product_media DROP CONSTRAINT FK_product_media_product');
        }
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE product');
        $this->addSql('DROP TABLE product_variant');
        $this->addSql('DROP TABLE product_media');
    }
}
