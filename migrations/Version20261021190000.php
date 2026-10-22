<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Optional variant per product image.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product_media ADD COLUMN variant_id INTEGER DEFAULT NULL REFERENCES product_variant (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_PRODUCT_MEDIA_VARIANT ON product_media (variant_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_PRODUCT_MEDIA_VARIANT');
        $this->addSql('ALTER TABLE product_media DROP COLUMN variant_id');
    }
}
