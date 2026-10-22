<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021130000_AddRatingSummaryToProducts extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add rating_average and rating_count to products';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            ALTER TABLE products
                ADD COLUMN rating_average NUMERIC(3, 2) DEFAULT NULL,
                ADD COLUMN rating_count INTEGER NOT NULL DEFAULT 0
        ");

        // Backfill from the ratings given before these columns existed
        $this->addSql("
            UPDATE products p
            SET rating_average = r.average, rating_count = r.total
            FROM (
                SELECT product_id, ROUND(AVG(stars), 2) AS average, COUNT(*) AS total
                FROM product_ratings
                GROUP BY product_id
            ) r
            WHERE r.product_id = p.id
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE products DROP COLUMN rating_average, DROP COLUMN rating_count");
    }
}
