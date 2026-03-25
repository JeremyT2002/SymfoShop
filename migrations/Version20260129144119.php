<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260129144119 extends AbstractMigration
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
        
        // `user` is a reserved keyword; escape per platform.
        $userTable = $isPostgres ? '"user"' : ($isSqlite ? '"user"' : '`user`');

        $timestampType = $isPostgres ? 'TIMESTAMP(0) WITHOUT TIME ZONE' : 'DATETIME';
        
        $this->addSql('ALTER TABLE '.$userTable.' ADD reset_token VARCHAR(255) DEFAULT NULL');
        if ($isSqlite) {
            $this->addSql('ALTER TABLE '.$userTable.' ADD reset_token_expires_at DATETIME DEFAULT NULL');
        } else {
            $this->addSql('ALTER TABLE '.$userTable.' ADD reset_token_expires_at '.$timestampType.' DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $platform = $this->connection->getDatabasePlatform();
        $isPostgres = $platform instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform;
        $isSqlite = $platform instanceof \Doctrine\DBAL\Platforms\SqlitePlatform;
        $userTable = $isPostgres ? '"user"' : ($isSqlite ? '"user"' : '`user`');

        $this->addSql('ALTER TABLE '.$userTable.' DROP reset_token');
        $this->addSql('ALTER TABLE '.$userTable.' DROP reset_token_expires_at');
    }
}
