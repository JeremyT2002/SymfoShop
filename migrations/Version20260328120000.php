<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260328120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create support conversation/message/attachment tables for customer support chat.';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $isPostgres = $platform instanceof PostgreSQLPlatform;
        $isSqlite = $platform instanceof SqlitePlatform;
        $userTable = $isPostgres || $isSqlite ? '"user"' : '`user`';

        $this->addSql('DROP TABLE IF EXISTS support_attachment');
        $this->addSql('DROP TABLE IF EXISTS support_message');
        $this->addSql('DROP TABLE IF EXISTS support_conversation');

        if ($isSqlite) {
            $this->addSql('CREATE TABLE support_conversation (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                customer_id INTEGER NOT NULL,
                subject VARCHAR(180) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT \'open\',
                customer_unread_count INTEGER NOT NULL DEFAULT 0,
                supporter_unread_count INTEGER NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                CONSTRAINT FK_support_conversation_customer FOREIGN KEY (customer_id) REFERENCES ' . $userTable . ' (id) ON DELETE CASCADE
            )');
            $this->addSql('CREATE INDEX idx_support_conversation_status_updated ON support_conversation (status, updated_at)');
            $this->addSql('CREATE INDEX idx_support_conversation_customer ON support_conversation (customer_id)');

            $this->addSql('CREATE TABLE support_message (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                conversation_id INTEGER NOT NULL,
                sender_user_id INTEGER DEFAULT NULL,
                sender_type VARCHAR(20) NOT NULL,
                body CLOB NOT NULL,
                created_at DATETIME NOT NULL,
                CONSTRAINT FK_support_message_conversation FOREIGN KEY (conversation_id) REFERENCES support_conversation (id) ON DELETE CASCADE,
                CONSTRAINT FK_support_message_sender FOREIGN KEY (sender_user_id) REFERENCES ' . $userTable . ' (id) ON DELETE SET NULL
            )');
            $this->addSql('CREATE INDEX idx_support_message_conversation_id ON support_message (conversation_id, id)');
            $this->addSql('CREATE INDEX idx_support_message_sender_user ON support_message (sender_user_id)');

            $this->addSql('CREATE TABLE support_attachment (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                message_id INTEGER NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                stored_name VARCHAR(255) NOT NULL,
                mime_type VARCHAR(120) NOT NULL,
                size_bytes INTEGER NOT NULL,
                created_at DATETIME NOT NULL,
                CONSTRAINT FK_support_attachment_message FOREIGN KEY (message_id) REFERENCES support_message (id) ON DELETE CASCADE
            )');
            $this->addSql('CREATE INDEX idx_support_attachment_message_id ON support_attachment (message_id)');

            return;
        }

        if ($isPostgres) {
            $this->addSql('CREATE TABLE support_conversation (
                id SERIAL NOT NULL,
                customer_id INT NOT NULL,
                subject VARCHAR(180) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT \'open\',
                customer_unread_count INT NOT NULL DEFAULT 0,
                supporter_unread_count INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )');
            $this->addSql('ALTER TABLE support_conversation ADD CONSTRAINT FK_support_conversation_customer FOREIGN KEY (customer_id) REFERENCES ' . $userTable . ' (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
            $this->addSql('CREATE INDEX idx_support_conversation_status_updated ON support_conversation (status, updated_at)');
            $this->addSql('CREATE INDEX idx_support_conversation_customer ON support_conversation (customer_id)');

            $this->addSql('CREATE TABLE support_message (
                id SERIAL NOT NULL,
                conversation_id INT NOT NULL,
                sender_user_id INT DEFAULT NULL,
                sender_type VARCHAR(20) NOT NULL,
                body TEXT NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )');
            $this->addSql('ALTER TABLE support_message ADD CONSTRAINT FK_support_message_conversation FOREIGN KEY (conversation_id) REFERENCES support_conversation (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
            $this->addSql('ALTER TABLE support_message ADD CONSTRAINT FK_support_message_sender FOREIGN KEY (sender_user_id) REFERENCES ' . $userTable . ' (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
            $this->addSql('CREATE INDEX idx_support_message_conversation_id ON support_message (conversation_id, id)');
            $this->addSql('CREATE INDEX idx_support_message_sender_user ON support_message (sender_user_id)');

            $this->addSql('CREATE TABLE support_attachment (
                id SERIAL NOT NULL,
                message_id INT NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                stored_name VARCHAR(255) NOT NULL,
                mime_type VARCHAR(120) NOT NULL,
                size_bytes INT NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )');
            $this->addSql('ALTER TABLE support_attachment ADD CONSTRAINT FK_support_attachment_message FOREIGN KEY (message_id) REFERENCES support_message (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
            $this->addSql('CREATE INDEX idx_support_attachment_message_id ON support_attachment (message_id)');

            return;
        }

        $this->addSql('CREATE TABLE support_conversation (
            id INTEGER NOT NULL AUTO_INCREMENT,
            customer_id INT NOT NULL,
            subject VARCHAR(180) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'open\',
            customer_unread_count INT NOT NULL DEFAULT 0,
            supporter_unread_count INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('ALTER TABLE support_conversation ADD CONSTRAINT FK_support_conversation_customer FOREIGN KEY (customer_id) REFERENCES ' . $userTable . ' (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX idx_support_conversation_status_updated ON support_conversation (status, updated_at)');
        $this->addSql('CREATE INDEX idx_support_conversation_customer ON support_conversation (customer_id)');

        $this->addSql('CREATE TABLE support_message (
            id INTEGER NOT NULL AUTO_INCREMENT,
            conversation_id INT NOT NULL,
            sender_user_id INT DEFAULT NULL,
            sender_type VARCHAR(20) NOT NULL,
            body LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('ALTER TABLE support_message ADD CONSTRAINT FK_support_message_conversation FOREIGN KEY (conversation_id) REFERENCES support_conversation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE support_message ADD CONSTRAINT FK_support_message_sender FOREIGN KEY (sender_user_id) REFERENCES ' . $userTable . ' (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX idx_support_message_conversation_id ON support_message (conversation_id, id)');
        $this->addSql('CREATE INDEX idx_support_message_sender_user ON support_message (sender_user_id)');

        $this->addSql('CREATE TABLE support_attachment (
            id INTEGER NOT NULL AUTO_INCREMENT,
            message_id INT NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            stored_name VARCHAR(255) NOT NULL,
            mime_type VARCHAR(120) NOT NULL,
            size_bytes INT NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('ALTER TABLE support_attachment ADD CONSTRAINT FK_support_attachment_message FOREIGN KEY (message_id) REFERENCES support_message (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX idx_support_attachment_message_id ON support_attachment (message_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS support_attachment');
        $this->addSql('DROP TABLE IF EXISTS support_message');
        $this->addSql('DROP TABLE IF EXISTS support_conversation');
    }
}

