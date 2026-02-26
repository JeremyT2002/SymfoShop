<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add category relationship to product table
 */
final class Version20260130000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add category_id column to product table';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $isPostgres = $platform instanceof PostgreSQLPlatform;
        $isSqlite = $platform instanceof SqlitePlatform;
        
        // Add category_id column to product table
        if ($isSqlite) {
            $this->addSql('ALTER TABLE product ADD COLUMN category_id INTEGER DEFAULT NULL');
            $this->addSql('CREATE INDEX idx_product_category ON product (category_id)');
            $this->addSql('CREATE INDEX FK_product_category ON product (category_id)');
        } else {
            $this->addSql('ALTER TABLE product ADD COLUMN category_id INT DEFAULT NULL');
            $this->addSql('CREATE INDEX idx_product_category ON product (category_id)');
            $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_product_category FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE SET NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $isPostgres = $platform instanceof PostgreSQLPlatform;
        $isSqlite = $platform instanceof SqlitePlatform;
        
        if ($isSqlite) {
            // SQLite doesn't support DROP COLUMN easily, so we'll just drop the index
            $this->addSql('DROP INDEX IF EXISTS idx_product_category');
            $this->addSql('DROP INDEX IF EXISTS FK_product_category');
        } else {
            $this->addSql('ALTER TABLE product DROP CONSTRAINT IF EXISTS FK_product_category');
            $this->addSql('DROP INDEX IF EXISTS idx_product_category');
            $this->addSql('ALTER TABLE product DROP COLUMN category_id');
        }
    }
}

