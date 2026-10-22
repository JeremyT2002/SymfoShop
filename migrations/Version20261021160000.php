<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Compare-at ("was") price on product variants.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product_variant ADD COLUMN compare_at_price_amount INTEGER DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product_variant DROP COLUMN compare_at_price_amount');
    }
}
