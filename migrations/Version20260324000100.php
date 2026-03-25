<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260324000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create payment_method table and seed Stripe/PayPal/TestBank.';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $isPostgres = $platform instanceof PostgreSQLPlatform;
        $isSqlite = $platform instanceof SqlitePlatform;

        // Note: Doctrine sometimes adds inline `--(DC2Type:...)` hints.
        // MariaDB only treats `--` as a comment when followed by whitespace,
        // so we remove those hints to keep the SQL valid.
        if ($isSqlite) {
            $this->addSql('CREATE TABLE payment_method (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                code VARCHAR(50) NOT NULL,
                name VARCHAR(120) NOT NULL,
                is_active BOOLEAN DEFAULT 1 NOT NULL,
                is_default BOOLEAN DEFAULT 0 NOT NULL,
                sort_order INTEGER DEFAULT 0 NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME DEFAULT NULL
            )');
        } elseif ($isPostgres) {
            $this->addSql('CREATE TABLE payment_method (
                id SERIAL NOT NULL,
                code VARCHAR(50) NOT NULL,
                name VARCHAR(120) NOT NULL,
                is_active BOOLEAN DEFAULT true NOT NULL,
                is_default BOOLEAN DEFAULT false NOT NULL,
                sort_order INTEGER DEFAULT 0 NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY(id)
            )');
        } else {
            $this->addSql('CREATE TABLE payment_method (
                id INTEGER NOT NULL AUTO_INCREMENT,
                code VARCHAR(50) NOT NULL,
                name VARCHAR(120) NOT NULL,
                is_active BOOLEAN DEFAULT 1 NOT NULL,
                is_default BOOLEAN DEFAULT 0 NOT NULL,
                sort_order INTEGER DEFAULT 0 NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME DEFAULT NULL,
                PRIMARY KEY(id)
            )');
        }
        $this->addSql('CREATE UNIQUE INDEX uniq_payment_method_code ON payment_method (code)');

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->addSql("INSERT INTO payment_method (code, name, is_active, is_default, sort_order, created_at) VALUES ('stripe', 'Stripe', 1, 1, 10, '$now')");
        $this->addSql("INSERT INTO payment_method (code, name, is_active, is_default, sort_order, created_at) VALUES ('paypal', 'PayPal', 1, 0, 20, '$now')");
        $this->addSql("INSERT INTO payment_method (code, name, is_active, is_default, sort_order, created_at) VALUES ('testbank', 'TestBank (Sandbox)', 1, 0, 30, '$now')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE payment_method');
    }
}

