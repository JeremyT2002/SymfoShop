<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260129133315 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create User table for authentication';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $isPostgres = $platform instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform;
        $isSqlite = $platform instanceof \Doctrine\DBAL\Platforms\SqlitePlatform;

        // `user` is a reserved keyword; escape per platform.
        $userTable = $isPostgres ? '"user"' : ($isSqlite ? '"user"' : '`user`');
        
        $idType = $isPostgres ? 'SERIAL' : 'INTEGER';
        $timestampType = $isPostgres ? 'TIMESTAMP(0) WITHOUT TIME ZONE' : 'DATETIME';
        $jsonType = $isPostgres ? 'JSON' : 'TEXT';
        $boolType = $isPostgres ? 'BOOLEAN' : 'INTEGER';
        $boolDefault = $isPostgres ? 'true' : '1';

        // Allow re-running after partial schema creation/failure.
        $this->addSql('DROP TABLE IF EXISTS '.$userTable);
        
        // Create user table
        if ($isSqlite) {
            $this->addSql("CREATE TABLE \"user\" (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                email VARCHAR(180) NOT NULL,
                roles TEXT NOT NULL,
                password VARCHAR(255) NOT NULL,
                first_name VARCHAR(255) DEFAULT NULL,
                last_name VARCHAR(255) DEFAULT NULL,
                is_active INTEGER DEFAULT 1 NOT NULL,
                created_at DATETIME NOT NULL,
                last_login_at DATETIME DEFAULT NULL,
                UNIQUE (email)
            )");
        } else {
            $this->addSql(
                'CREATE TABLE '.$userTable.' (
                    id '.$idType.' NOT NULL,
                    email VARCHAR(180) NOT NULL,
                    roles '.$jsonType.' NOT NULL,
                    password VARCHAR(255) NOT NULL,
                    first_name VARCHAR(255) DEFAULT NULL,
                    last_name VARCHAR(255) DEFAULT NULL,
                    is_active '.$boolType.' DEFAULT '.$boolDefault.' NOT NULL,
                    created_at '.$timestampType.' NOT NULL,
                    last_login_at '.$timestampType.' DEFAULT NULL,
                    UNIQUE (email),
                    PRIMARY KEY(id)
                )'
            );
        }
        $this->addSql('CREATE INDEX idx_user_email ON '.$userTable.' (email)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE `user`');
    }
}
