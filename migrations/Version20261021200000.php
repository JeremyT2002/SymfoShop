<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Newsletter subscribers (double opt-in).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE newsletter_subscriber (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(255) NOT NULL, token VARCHAR(64) NOT NULL, locale VARCHAR(10) NOT NULL, created_at DATETIME NOT NULL, confirmed_at DATETIME DEFAULT NULL, unsubscribed_at DATETIME DEFAULT NULL)');
        $this->addSql('CREATE UNIQUE INDEX uniq_newsletter_email ON newsletter_subscriber (email)');
        $this->addSql('CREATE UNIQUE INDEX uniq_newsletter_token ON newsletter_subscriber (token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE newsletter_subscriber');
    }
}
