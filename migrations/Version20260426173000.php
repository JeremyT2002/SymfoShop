<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260426173000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add persistent carts and user marketing preference for abandoned cart reminders.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD COLUMN marketing_opt_in BOOLEAN NOT NULL DEFAULT 0');
        $this->addSql('CREATE TABLE cart (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, reminder_sent_at DATETIME DEFAULT NULL, CONSTRAINT FK_CART_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX idx_cart_updated ON cart (updated_at)');
        $this->addSql('CREATE INDEX IDX_BA388B793A76ED395 ON cart (user_id)');
        $this->addSql('CREATE TABLE cart_item (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, cart_id INTEGER NOT NULL, product_variant_id INTEGER NOT NULL, quantity INTEGER NOT NULL, CONSTRAINT FK_CART_ITEM_CART FOREIGN KEY (cart_id) REFERENCES cart (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_CART_ITEM_VARIANT FOREIGN KEY (product_variant_id) REFERENCES product_variant (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_F0FE25271AD5CDBF ON cart_item (cart_id)');
        $this->addSql('CREATE INDEX IDX_F0FE25278D93D649 ON cart_item (product_variant_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE cart_item');
        $this->addSql('DROP TABLE cart');
        $this->addSql('ALTER TABLE user DROP COLUMN marketing_opt_in');
    }
}

