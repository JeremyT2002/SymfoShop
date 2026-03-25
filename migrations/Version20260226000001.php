<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create admin_dashboard_config table for customizable dashboard layout and nav
 */
final class Version20260226000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create admin_dashboard_config table';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $isPostgres = $platform instanceof PostgreSQLPlatform;
        $isSqlite = $platform instanceof SqlitePlatform;

        // `user` is a reserved keyword; escape identifiers per platform.
        $userTable = $isPostgres ? '"user"' : ($isSqlite ? '"user"' : '`user`');

        // Re-runnable: allow clean re-try after a failed run.
        $this->addSql('DROP TABLE IF EXISTS admin_dashboard_config');

        if ($isSqlite) {
            $this->addSql('CREATE TABLE admin_dashboard_config (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                owner_id INTEGER DEFAULT NULL,
                config_json CLOB NOT NULL,
                version INTEGER DEFAULT 1 NOT NULL,
                updated_at DATETIME NOT NULL,
                CONSTRAINT FK_admin_dashboard_config_owner FOREIGN KEY (owner_id) REFERENCES '.$userTable.' (id) ON DELETE CASCADE
            )');
            $this->addSql('CREATE UNIQUE INDEX uniq_admin_dashboard_config_owner ON admin_dashboard_config (owner_id)');
            $this->addSql('CREATE INDEX idx_admin_dashboard_config_owner ON admin_dashboard_config (owner_id)');
        } else {
            $this->addSql('CREATE TABLE admin_dashboard_config (
                id SERIAL PRIMARY KEY NOT NULL,
                owner_id INT DEFAULT NULL,
                config_json JSON NOT NULL,
                version INT DEFAULT 1 NOT NULL,
                updated_at TIMESTAMP(0) NOT NULL,
                CONSTRAINT FK_admin_dashboard_config_owner FOREIGN KEY (owner_id) REFERENCES '.$userTable.' (id) ON DELETE CASCADE
            )');
            $this->addSql('CREATE UNIQUE INDEX uniq_admin_dashboard_config_owner ON admin_dashboard_config (owner_id)');
            $this->addSql('CREATE INDEX idx_admin_dashboard_config_owner ON admin_dashboard_config (owner_id)');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS admin_dashboard_config');
    }
}
