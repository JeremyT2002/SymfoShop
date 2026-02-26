<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260129202352 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $isPostgres = $platform instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform;
        $isSqlite = $platform instanceof \Doctrine\DBAL\Platforms\SqlitePlatform;
        
        $idType = $isPostgres ? 'SERIAL' : 'INTEGER';
        $timestampType = $isPostgres ? 'TIMESTAMP(0) WITHOUT TIME ZONE' : 'DATETIME';
        $jsonType = $isPostgres ? 'JSON' : 'TEXT';
        $boolType = $isPostgres ? 'BOOLEAN' : 'INTEGER';
        $boolDefault = $isPostgres ? 'true' : '1';
        
        // Create api_key table
        if ($isSqlite) {
            $this->addSql("CREATE TABLE api_key (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                name VARCHAR(255) NOT NULL,
                key_hash VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL,
                expires_at DATETIME DEFAULT NULL,
                last_used_at DATETIME DEFAULT NULL,
                is_active INTEGER DEFAULT 1 NOT NULL,
                scopes TEXT DEFAULT NULL,
                user_id INTEGER NOT NULL,
                UNIQUE (key_hash),
                CONSTRAINT FK_C912ED9DA76ED395 FOREIGN KEY (user_id) REFERENCES \"user\" (id) ON DELETE CASCADE
            )");
        } else {
            $this->addSql("CREATE TABLE api_key (
                id {$idType} NOT NULL,
                name VARCHAR(255) NOT NULL,
                key_hash VARCHAR(255) NOT NULL,
                created_at {$timestampType} NOT NULL,
                expires_at {$timestampType} DEFAULT NULL,
                last_used_at {$timestampType} DEFAULT NULL,
                is_active {$boolType} DEFAULT {$boolDefault} NOT NULL,
                scopes {$jsonType} DEFAULT NULL,
                user_id INT NOT NULL,
                PRIMARY KEY(id)
            )");
            $this->addSql('ALTER TABLE api_key ADD CONSTRAINT FK_C912ED9DA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE');
        }
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C912ED9D57BFB971 ON api_key (key_hash)');
        $this->addSql('CREATE INDEX idx_api_key_hash ON api_key (key_hash)');
        $this->addSql('CREATE INDEX idx_api_key_user ON api_key (user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE api_key');
    }
}
