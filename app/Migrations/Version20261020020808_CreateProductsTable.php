<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261020020808_CreateProductsTable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create products table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE products (
                id UUID PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                sku VARCHAR(64) NOT NULL UNIQUE,
                description TEXT DEFAULT NULL,
                price NUMERIC(10, 2) NOT NULL,
                stock INTEGER NOT NULL,
                active BOOLEAN NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE products");
    }
}
