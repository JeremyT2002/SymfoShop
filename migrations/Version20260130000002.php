<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260130000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create coupon and coupon_usage tables for discount codes';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $isPostgres = $platform instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform;
        $isSqlite = $platform instanceof \Doctrine\DBAL\Platforms\SqlitePlatform;
        
        $idType = $isPostgres ? 'SERIAL' : 'INTEGER';
        $timestampType = $isPostgres ? 'TIMESTAMP(0) WITHOUT TIME ZONE' : 'DATETIME';
        $boolType = $isPostgres ? 'BOOLEAN' : 'INTEGER';
        $boolDefault = $isPostgres ? 'true' : '1';
        
        // Create coupon table
        if ($isSqlite) {
            $this->addSql("CREATE TABLE coupon (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                code VARCHAR(50) NOT NULL,
                type VARCHAR(20) NOT NULL,
                value INTEGER NOT NULL,
                expires_at DATETIME DEFAULT NULL,
                usage_limit INTEGER DEFAULT NULL,
                per_user_limit INTEGER DEFAULT NULL,
                is_active INTEGER DEFAULT 1 NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE (code)
            )");
        } else {
            $this->addSql("CREATE TABLE coupon (
                id {$idType} NOT NULL,
                code VARCHAR(50) NOT NULL,
                type VARCHAR(20) NOT NULL,
                value INTEGER NOT NULL,
                expires_at {$timestampType} DEFAULT NULL,
                usage_limit INTEGER DEFAULT NULL,
                per_user_limit INTEGER DEFAULT NULL,
                is_active {$boolType} DEFAULT {$boolDefault} NOT NULL,
                created_at {$timestampType} NOT NULL,
                updated_at {$timestampType} NOT NULL,
                PRIMARY KEY(id)
            )");
        }
        
        $this->addSql('CREATE UNIQUE INDEX UNIQ_64BF3F0277153098 ON coupon (code)');
        $this->addSql('CREATE INDEX idx_coupon_code ON coupon (code)');
        $this->addSql('CREATE INDEX idx_coupon_active ON coupon (is_active)');
        
        // Create coupon_usage table
        if ($isSqlite) {
            $this->addSql("CREATE TABLE coupon_usage (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                coupon_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                usage_count INTEGER DEFAULT 1 NOT NULL,
                used_at DATETIME NOT NULL,
                CONSTRAINT FK_COUPON_USAGE_COUPON FOREIGN KEY (coupon_id) REFERENCES coupon (id) ON DELETE CASCADE,
                CONSTRAINT FK_COUPON_USAGE_USER FOREIGN KEY (user_id) REFERENCES \"user\" (id) ON DELETE CASCADE
            )");
        } else {
            $this->addSql("CREATE TABLE coupon_usage (
                id {$idType} NOT NULL,
                coupon_id INT NOT NULL,
                user_id INT NOT NULL,
                usage_count INTEGER DEFAULT 1 NOT NULL,
                used_at {$timestampType} NOT NULL,
                PRIMARY KEY(id)
            )");
            $this->addSql('ALTER TABLE coupon_usage ADD CONSTRAINT FK_COUPON_USAGE_COUPON FOREIGN KEY (coupon_id) REFERENCES coupon (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE coupon_usage ADD CONSTRAINT FK_COUPON_USAGE_USER FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE');
        }
        
        $this->addSql('CREATE UNIQUE INDEX coupon_user_unique ON coupon_usage (coupon_id, user_id)');
        $this->addSql('CREATE INDEX idx_coupon_usage_coupon ON coupon_usage (coupon_id)');
        $this->addSql('CREATE INDEX idx_coupon_usage_user ON coupon_usage (user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE coupon_usage');
        $this->addSql('DROP TABLE coupon');
    }
}

