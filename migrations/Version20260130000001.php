<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260130000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create wishlist table for user product favorites';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $isPostgres = $platform instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform;
        $isSqlite = $platform instanceof \Doctrine\DBAL\Platforms\SqlitePlatform;
        
        $idType = $isPostgres ? 'SERIAL' : 'INTEGER';
        $timestampType = $isPostgres ? 'TIMESTAMP(0) WITHOUT TIME ZONE' : 'DATETIME';
        
        // Create wishlist table
        if ($isSqlite) {
            $this->addSql("CREATE TABLE wishlist (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                user_id INTEGER NOT NULL,
                product_id INTEGER NOT NULL,
                created_at DATETIME NOT NULL,
                CONSTRAINT FK_9CE58A1DA76ED395 FOREIGN KEY (user_id) REFERENCES \"user\" (id) ON DELETE CASCADE,
                CONSTRAINT FK_9CE58A1D4584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE
            )");
        } else {
            $this->addSql("CREATE TABLE wishlist (
                id {$idType} NOT NULL,
                user_id INT NOT NULL,
                product_id INT NOT NULL,
                created_at {$timestampType} NOT NULL,
                PRIMARY KEY(id)
            )");
            $this->addSql('ALTER TABLE wishlist ADD CONSTRAINT FK_9CE58A1DA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE wishlist ADD CONSTRAINT FK_9CE58A1D4584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        }
        
        $this->addSql('CREATE UNIQUE INDEX user_product_unique ON wishlist (user_id, product_id)');
        $this->addSql('CREATE INDEX idx_wishlist_user ON wishlist (user_id)');
        $this->addSql('CREATE INDEX idx_wishlist_product ON wishlist (product_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE wishlist');
    }
}

