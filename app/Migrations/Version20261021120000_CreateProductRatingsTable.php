<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021120000_CreateProductRatingsTable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create product_ratings table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE product_ratings (
                id UUID PRIMARY KEY,
                product_id UUID NOT NULL REFERENCES products (id) ON DELETE CASCADE,
                rater VARCHAR(128) NOT NULL,
                stars SMALLINT NOT NULL CHECK (stars BETWEEN 1 AND 5),
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                CONSTRAINT product_ratings_product_rater_key UNIQUE (product_id, rater)
            )
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE product_ratings");
    }
}
