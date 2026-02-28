<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Theme Editor: shop, theme, theme_revision tables for multi-tenant theme configuration
 */
final class Version20260226120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'feat(theme): add theme configuration model - shop, theme, theme_revision tables';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $isPostgres = $platform instanceof PostgreSQLPlatform;
        $isSqlite = $platform instanceof SqlitePlatform;

        if ($isSqlite) {
            $this->addSql('CREATE TABLE shop (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                name VARCHAR(100) NOT NULL,
                slug VARCHAR(100) NOT NULL,
                is_active BOOLEAN DEFAULT 1 NOT NULL,
                created_at DATETIME NOT NULL
            )');
            $this->addSql('CREATE UNIQUE INDEX uniq_shop_slug ON shop (slug)');
            $this->addSql('CREATE INDEX idx_shop_slug ON shop (slug)');

            $this->addSql('CREATE TABLE theme (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                shop_id INTEGER DEFAULT NULL,
                name VARCHAR(100) NOT NULL,
                slug VARCHAR(100) NOT NULL,
                status VARCHAR(20) DEFAULT \'draft\' NOT NULL,
                config CLOB NOT NULL,
                version INTEGER DEFAULT 1 NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                CONSTRAINT FK_theme_shop FOREIGN KEY (shop_id) REFERENCES shop (id) ON DELETE CASCADE
            )');
            $this->addSql('CREATE UNIQUE INDEX uniq_theme_shop_slug ON theme (shop_id, slug)');
            $this->addSql('CREATE INDEX idx_theme_shop ON theme (shop_id)');
            $this->addSql('CREATE INDEX idx_theme_status ON theme (status)');

            $this->addSql('CREATE TABLE theme_revision (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                theme_id INTEGER NOT NULL,
                config CLOB NOT NULL,
                version INTEGER NOT NULL,
                status VARCHAR(20) DEFAULT \'draft\' NOT NULL,
                published_at DATETIME DEFAULT NULL,
                comment VARCHAR(500) DEFAULT NULL,
                created_by_id INTEGER DEFAULT NULL,
                created_at DATETIME NOT NULL,
                CONSTRAINT FK_theme_revision_theme FOREIGN KEY (theme_id) REFERENCES theme (id) ON DELETE CASCADE,
                CONSTRAINT FK_theme_revision_created_by FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL
            )');
            $this->addSql('CREATE INDEX idx_theme_revision_theme ON theme_revision (theme_id)');
            $this->addSql('CREATE INDEX idx_theme_revision_theme_version ON theme_revision (theme_id, version)');
        } else {
            $this->addSql('CREATE TABLE shop (
                id SERIAL PRIMARY KEY NOT NULL,
                name VARCHAR(100) NOT NULL,
                slug VARCHAR(100) NOT NULL,
                is_active BOOLEAN DEFAULT true NOT NULL,
                created_at TIMESTAMP(0) NOT NULL
            )');
            $this->addSql('CREATE UNIQUE INDEX uniq_shop_slug ON shop (slug)');
            $this->addSql('CREATE INDEX idx_shop_slug ON shop (slug)');

            $this->addSql('CREATE TABLE theme (
                id SERIAL PRIMARY KEY NOT NULL,
                shop_id INT DEFAULT NULL,
                name VARCHAR(100) NOT NULL,
                slug VARCHAR(100) NOT NULL,
                status VARCHAR(20) DEFAULT \'draft\' NOT NULL,
                config JSON NOT NULL,
                version INT DEFAULT 1 NOT NULL,
                created_at TIMESTAMP(0) NOT NULL,
                updated_at TIMESTAMP(0) NOT NULL,
                CONSTRAINT FK_theme_shop FOREIGN KEY (shop_id) REFERENCES shop (id) ON DELETE CASCADE
            )');
            $this->addSql('CREATE UNIQUE INDEX uniq_theme_shop_slug ON theme (shop_id, slug)');
            $this->addSql('CREATE INDEX idx_theme_shop ON theme (shop_id)');
            $this->addSql('CREATE INDEX idx_theme_status ON theme (status)');

            $this->addSql('CREATE TABLE theme_revision (
                id SERIAL PRIMARY KEY NOT NULL,
                theme_id INT NOT NULL,
                config JSON NOT NULL,
                version INT NOT NULL,
                status VARCHAR(20) DEFAULT \'draft\' NOT NULL,
                published_at TIMESTAMP(0) DEFAULT NULL,
                comment VARCHAR(500) DEFAULT NULL,
                created_by_id INT DEFAULT NULL,
                created_at TIMESTAMP(0) NOT NULL,
                CONSTRAINT FK_theme_revision_theme FOREIGN KEY (theme_id) REFERENCES theme (id) ON DELETE CASCADE,
                CONSTRAINT FK_theme_revision_created_by FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL
            )');
            $this->addSql('CREATE INDEX idx_theme_revision_theme ON theme_revision (theme_id)');
            $this->addSql('CREATE INDEX idx_theme_revision_theme_version ON theme_revision (theme_id, version)');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS theme_revision');
        $this->addSql('DROP TABLE IF EXISTS theme');
        $this->addSql('DROP TABLE IF EXISTS shop');
    }
}
