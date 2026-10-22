<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261022120000_AddProductsCreatedAtIndex extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index the products listing order (newest first)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE INDEX products_created_at_id_idx ON products (created_at, id)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP INDEX products_created_at_id_idx");
    }
}
