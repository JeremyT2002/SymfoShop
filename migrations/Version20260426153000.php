<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260426153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add product reviews and strict verified-purchase relations.';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $isPostgres = $platform instanceof PostgreSQLPlatform;
        $isSqlite = $platform instanceof SqlitePlatform;

        if ($isSqlite) {
            $this->addSql('ALTER TABLE "order" ADD COLUMN user_id INTEGER DEFAULT NULL');
            $this->addSql('ALTER TABLE order_item ADD COLUMN product_variant_id INTEGER DEFAULT NULL');

            $this->addSql('CREATE TABLE product_review (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, product_id INTEGER NOT NULL, user_id INTEGER NOT NULL, rating SMALLINT NOT NULL, title VARCHAR(120) NOT NULL, body CLOB NOT NULL, created_at DATETIME NOT NULL, is_approved BOOLEAN NOT NULL DEFAULT 0, is_verified_purchase BOOLEAN NOT NULL DEFAULT 0, CONSTRAINT FK_PRODUCT_REVIEW_PRODUCT FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_PRODUCT_REVIEW_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
            $this->addSql('CREATE UNIQUE INDEX uniq_product_review_product_user ON product_review (product_id, user_id)');
            $this->addSql('CREATE INDEX idx_product_review_approved_created ON product_review (is_approved, created_at)');
            $this->addSql('CREATE INDEX idx_order_user ON "order" (user_id)');
            $this->addSql('CREATE INDEX idx_order_item_product_variant ON order_item (product_variant_id)');
            $this->addSql('CREATE INDEX IDX_FD9E6F191D775834 ON "order" (user_id)');
            $this->addSql('CREATE INDEX IDX_52EA1F098D93D649 ON order_item (product_variant_id)');
            return;
        }

        if ($isPostgres) {
            $this->addSql('ALTER TABLE "order" ADD COLUMN user_id INT DEFAULT NULL');
            $this->addSql('ALTER TABLE order_item ADD COLUMN product_variant_id INT DEFAULT NULL');
            $this->addSql('ALTER TABLE "order" ADD CONSTRAINT FK_F52993981D775834 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
            $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_52EA1F098D93D649 FOREIGN KEY (product_variant_id) REFERENCES product_variant (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

            $this->addSql('CREATE TABLE product_review (id SERIAL NOT NULL, product_id INT NOT NULL, user_id INT NOT NULL, rating SMALLINT NOT NULL, title VARCHAR(120) NOT NULL, body TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, is_approved BOOLEAN DEFAULT FALSE NOT NULL, is_verified_purchase BOOLEAN DEFAULT FALSE NOT NULL, PRIMARY KEY(id))');
            $this->addSql('CREATE UNIQUE INDEX uniq_product_review_product_user ON product_review (product_id, user_id)');
            $this->addSql('CREATE INDEX idx_product_review_approved_created ON product_review (is_approved, created_at)');
            $this->addSql('CREATE INDEX IDX_BC0912ED4584665A ON product_review (product_id)');
            $this->addSql('CREATE INDEX IDX_BC0912EDA76ED395 ON product_review (user_id)');
            $this->addSql('ALTER TABLE product_review ADD CONSTRAINT FK_BC0912ED4584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
            $this->addSql('ALTER TABLE product_review ADD CONSTRAINT FK_BC0912EDA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
            $this->addSql('CREATE INDEX IDX_F52993981D775834 ON "order" (user_id)');
            $this->addSql('CREATE INDEX IDX_52EA1F098D93D649 ON order_item (product_variant_id)');
            return;
        }

        $this->addSql('ALTER TABLE `order` ADD COLUMN user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE order_item ADD COLUMN product_variant_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_F52993981D775834 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_52EA1F098D93D649 FOREIGN KEY (product_variant_id) REFERENCES product_variant (id) ON DELETE SET NULL');

        $this->addSql('CREATE TABLE product_review (id INT AUTO_INCREMENT NOT NULL, product_id INT NOT NULL, user_id INT NOT NULL, rating SMALLINT NOT NULL, title VARCHAR(120) NOT NULL, body LONGTEXT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', is_approved TINYINT(1) DEFAULT 0 NOT NULL, is_verified_purchase TINYINT(1) DEFAULT 0 NOT NULL, INDEX IDX_BC0912ED4584665A (product_id), INDEX IDX_BC0912EDA76ED395 (user_id), UNIQUE INDEX uniq_product_review_product_user (product_id, user_id), INDEX idx_product_review_approved_created (is_approved, created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE product_review ADD CONSTRAINT FK_BC0912ED4584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_review ADD CONSTRAINT FK_BC0912EDA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_F52993981D775834 ON `order` (user_id)');
        $this->addSql('CREATE INDEX IDX_52EA1F098D93D649 ON order_item (product_variant_id)');
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $isPostgres = $platform instanceof PostgreSQLPlatform;
        $isSqlite = $platform instanceof SqlitePlatform;

        if ($isSqlite) {
            $this->addSql('DROP INDEX IF EXISTS idx_product_review_approved_created');
            $this->addSql('DROP INDEX IF EXISTS uniq_product_review_product_user');
            $this->addSql('DROP TABLE IF EXISTS product_review');
            $this->addSql('DROP INDEX IF EXISTS IDX_FD9E6F191D775834');
            $this->addSql('DROP INDEX IF EXISTS IDX_52EA1F098D93D649');
            $this->addSql('ALTER TABLE "order" DROP COLUMN user_id');
            $this->addSql('ALTER TABLE order_item DROP COLUMN product_variant_id');
            return;
        }

        if ($isPostgres) {
            $this->addSql('ALTER TABLE product_review DROP CONSTRAINT FK_BC0912ED4584665A');
            $this->addSql('ALTER TABLE product_review DROP CONSTRAINT FK_BC0912EDA76ED395');
            $this->addSql('DROP TABLE product_review');
            $this->addSql('ALTER TABLE "order" DROP CONSTRAINT FK_F52993981D775834');
            $this->addSql('ALTER TABLE order_item DROP CONSTRAINT FK_52EA1F098D93D649');
            $this->addSql('DROP INDEX IDX_F52993981D775834');
            $this->addSql('DROP INDEX IDX_52EA1F098D93D649');
            $this->addSql('ALTER TABLE "order" DROP COLUMN user_id');
            $this->addSql('ALTER TABLE order_item DROP COLUMN product_variant_id');
            return;
        }

        $this->addSql('ALTER TABLE product_review DROP FOREIGN KEY FK_BC0912ED4584665A');
        $this->addSql('ALTER TABLE product_review DROP FOREIGN KEY FK_BC0912EDA76ED395');
        $this->addSql('DROP TABLE product_review');
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F52993981D775834');
        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY FK_52EA1F098D93D649');
        $this->addSql('DROP INDEX IDX_F52993981D775834 ON `order`');
        $this->addSql('DROP INDEX IDX_52EA1F098D93D649 ON order_item');
        $this->addSql('ALTER TABLE `order` DROP COLUMN user_id');
        $this->addSql('ALTER TABLE order_item DROP COLUMN product_variant_id');
    }
}

