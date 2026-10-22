<?php
// Recreates the Elasticsearch products index and indexes every product from the database.
// Reads raw rows in id order (keyset pagination), so it scales to tables with millions of products.
// Usage: php bin/reindex-products.php

require dirname(__DIR__) . '/app/bootstrap.php';

const BATCH_SIZE = 5000;

$connection = $entityManager->getConnection();
// Planner estimate: exact enough for progress and much cheaper than COUNT(*) on large tables
$estimate = max(1, (int) $connection->fetchOne("SELECT reltuples FROM pg_class WHERE oid = 'products'::regclass"));

$productIndex->recreate();
$productIndex->beginBulkLoad();

$total = 0;
$lastId = '00000000-0000-0000-0000-000000000000';
$start = microtime(true);
do {
    $rows = $connection->fetchAllAssociative(
        'SELECT id, name, sku, description, price, stock, active, rating_average, rating_count, created_at, updated_at
         FROM products WHERE id > :last ORDER BY id LIMIT ' . BATCH_SIZE,
        ['last' => $lastId],
    );
    if (!$rows) {
        break;
    }

    $productIndex->indexRows($rows);
    $total += count($rows);
    $lastId = end($rows)['id'];

    $elapsed = microtime(true) - $start;
    $rate = $total / $elapsed;
    printf(
        "\rIndexed %s of ~%s products (%s/s, ETA %s)   ",
        number_format($total), number_format($estimate), number_format($rate), gmdate('H:i:s', (int) (max(0, $estimate - $total) / $rate)),
    );
} while (count($rows) === BATCH_SIZE);

$productIndex->endBulkLoad();

printf("\nIndexed %s products into \"%s\" in %s.\n", number_format($total), $productIndex::NAME, gmdate('H:i:s', (int) (microtime(true) - $start)));
