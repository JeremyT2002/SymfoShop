<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Early migrations used INTEGER PRIMARY KEY without AUTO_INCREMENT on MySQL/MariaDB,
 * so INSERT without explicit id fails (e.g. doctrine:fixtures:load).
 */
final class Version20260326120100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'MySQL/MariaDB: add AUTO_INCREMENT to integer id columns missing it';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if ($platform instanceof PostgreSQLPlatform || $platform instanceof SqlitePlatform) {
            return;
        }

        $this->addSql('SET FOREIGN_KEY_CHECKS=0');

        $tables = [
            'admin_dashboard_config',
            'api_key',
            'audit_log',
            'category',
            'coupon',
            'coupon_usage',
            'customer',
            'invoice',
            'order_item',
            'payment',
            'processed_webhook_event',
            'product',
            'product_media',
            'product_variant',
            'order_reservation',
            'shop',
            'stock_item',
            'theme',
            'theme_revision',
            'wishlist',
        ];

        foreach ($tables as $table) {
            $this->addSql(sprintf('ALTER TABLE `%s` MODIFY `id` INT NOT NULL AUTO_INCREMENT', $table));
        }

        $this->addSql('ALTER TABLE `order` MODIFY `id` INT NOT NULL AUTO_INCREMENT');
        $this->addSql('ALTER TABLE `user` MODIFY `id` INT NOT NULL AUTO_INCREMENT');

        $this->addSql('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if ($platform instanceof PostgreSQLPlatform || $platform instanceof SqlitePlatform) {
            return;
        }

        $this->addSql('SET FOREIGN_KEY_CHECKS=0');

        $tables = [
            'admin_dashboard_config',
            'api_key',
            'audit_log',
            'category',
            'coupon',
            'coupon_usage',
            'customer',
            'invoice',
            'order_item',
            'payment',
            'processed_webhook_event',
            'product',
            'product_media',
            'product_variant',
            'order_reservation',
            'shop',
            'stock_item',
            'theme',
            'theme_revision',
            'wishlist',
        ];

        foreach ($tables as $table) {
            $this->addSql(sprintf('ALTER TABLE `%s` MODIFY `id` INT NOT NULL', $table));
        }

        $this->addSql('ALTER TABLE `order` MODIFY `id` INT NOT NULL');
        $this->addSql('ALTER TABLE `user` MODIFY `id` INT NOT NULL');

        $this->addSql('SET FOREIGN_KEY_CHECKS=1');
    }
}
