<?php
// Recreates the Elasticsearch products index and indexes every product from the database.
// Usage: php bin/reindex-products.php

use App\Entity\Product;

require dirname(__DIR__) . '/app/bootstrap.php';

const BATCH_SIZE = 500;

$productIndex->recreate();

$total = 0;
$batch = [];
$products = $entityManager->createQuery('SELECT p FROM ' . Product::class . ' p')->toIterable();
foreach ($products as $product) {
    $batch[] = $product;
    if (count($batch) === BATCH_SIZE) {
        $productIndex->sync($batch);
        $total += count($batch);
        $batch = [];
        $entityManager->clear();
    }
}
$productIndex->sync($batch);
$total += count($batch);

echo "Indexed $total products into \"" . $productIndex::NAME . "\".\n";
