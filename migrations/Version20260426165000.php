<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260426165000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add stock notifications with guest double opt-in fields.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE stock_notification (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, product_variant_id INTEGER NOT NULL, user_id INTEGER DEFAULT NULL, email VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, notified_at DATETIME DEFAULT NULL, confirmation_token VARCHAR(128) DEFAULT NULL, confirmed_at DATETIME DEFAULT NULL, token_expires_at DATETIME DEFAULT NULL, CONSTRAINT FK_STOCK_NOTIFICATION_VARIANT FOREIGN KEY (product_variant_id) REFERENCES product_variant (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_STOCK_NOTIFICATION_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX idx_stock_notification_variant_notified ON stock_notification (product_variant_id, notified_at)');
        $this->addSql('CREATE INDEX idx_stock_notification_token ON stock_notification (confirmation_token)');
        $this->addSql('CREATE INDEX IDX_5912173F8D93D649 ON stock_notification (product_variant_id)');
        $this->addSql('CREATE INDEX IDX_5912173FA76ED395 ON stock_notification (user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE stock_notification');
    }
}

