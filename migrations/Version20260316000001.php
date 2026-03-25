<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260316000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'feat(user): add customer profile and address fields to user entity';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $userTable = $platform instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform ? '"user"' : '`user`';

        $this->addSql('ALTER TABLE '.$userTable.' ADD phone VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE '.$userTable.' ADD company VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE '.$userTable.' ADD address_line1 VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE '.$userTable.' ADD address_line2 VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE '.$userTable.' ADD postal_code VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE '.$userTable.' ADD city VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE '.$userTable.' ADD state VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE '.$userTable.' ADD country_code VARCHAR(2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $userTable = $platform instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform ? '"user"' : '`user`';

        $this->addSql('ALTER TABLE '.$userTable.' DROP country_code');
        $this->addSql('ALTER TABLE '.$userTable.' DROP state');
        $this->addSql('ALTER TABLE '.$userTable.' DROP city');
        $this->addSql('ALTER TABLE '.$userTable.' DROP postal_code');
        $this->addSql('ALTER TABLE '.$userTable.' DROP address_line2');
        $this->addSql('ALTER TABLE '.$userTable.' DROP address_line1');
        $this->addSql('ALTER TABLE '.$userTable.' DROP company');
        $this->addSql('ALTER TABLE '.$userTable.' DROP phone');
    }
}

