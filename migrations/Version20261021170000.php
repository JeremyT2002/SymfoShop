<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Free-shipping threshold per shipping method.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shipping_method ADD COLUMN free_above_cents INTEGER DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shipping_method DROP COLUMN free_above_cents');
    }
}
