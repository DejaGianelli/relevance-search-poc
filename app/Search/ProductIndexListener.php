<?php

namespace App\Search;

use App\Entity\Product;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;

/**
 * Keeps the products index in sync with the database: products created, updated (including their
 * rating summary) or removed in a flush are sent to Elasticsearch once the flush finishes.
 *
 * The database is the source of truth, so an Elasticsearch failure is logged instead of failing the
 * request; bin/reindex-products.php rebuilds the index from the database.
 */
class ProductIndexListener
{
    /** @var array<string, Product> */
    private array $save = [];

    /** @var array<string, string> */
    private array $delete = [];

    public function __construct(private ProductIndex $index) {}

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->scheduleSave($args->getObject());
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->scheduleSave($args->getObject());
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $product = $args->getObject();
        if ($product instanceof Product) {
            $id = $product->getId()->toRfc4122();
            unset($this->save[$id]);
            $this->delete[$id] = $id;
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if (!$this->save && !$this->delete) {
            return;
        }

        [$save, $delete] = [array_values($this->save), array_values($this->delete)];
        $this->save = $this->delete = [];

        try {
            $this->index->sync($save, $delete);
        } catch (\Throwable $e) {
            error_log('Could not sync products index: ' . $e->getMessage());
        }
    }

    private function scheduleSave(object $entity): void
    {
        if ($entity instanceof Product) {
            $this->save[$entity->getId()->toRfc4122()] = $entity;
        }
    }
}
