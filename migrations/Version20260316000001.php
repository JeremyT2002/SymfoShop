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
        $this->addSql('ALTER TABLE "user" ADD phone VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD company VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD address_line1 VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD address_line2 VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD postal_code VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD city VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD state VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD country_code VARCHAR(2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP country_code');
        $this->addSql('ALTER TABLE "user" DROP state');
        $this->addSql('ALTER TABLE "user" DROP city');
        $this->addSql('ALTER TABLE "user" DROP postal_code');
        $this->addSql('ALTER TABLE "user" DROP address_line2');
        $this->addSql('ALTER TABLE "user" DROP address_line1');
        $this->addSql('ALTER TABLE "user" DROP company');
        $this->addSql('ALTER TABLE "user" DROP phone');
    }
}

