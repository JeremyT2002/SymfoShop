<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260129202530 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE api_key (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, key_hash VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, expires_at DATETIME DEFAULT NULL, last_used_at DATETIME DEFAULT NULL, is_active BOOLEAN DEFAULT 1 NOT NULL, scopes CLOB DEFAULT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_C912ED9DA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
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
