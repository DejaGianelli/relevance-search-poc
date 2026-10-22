# Simple Ecommerce Product Search Relevance Experiment

This is an experiment that emulates an ecommerce search products by relevance funcionality, uising elastic search as engine.

## Technologies and Tools

- PHP 8.3
- Twig
- Tailwind
- Symfony Components

## Running in Development

```shell
# Get infra up and running with docker
docker compose up -d

# Install PHP dependencies
composer update

# Install Node dependencias
npm install

# Run Tailwind CLI
npx @tailwindcss/cli -i ./css/style.css -o ./public/styles.css --watch
```

## Migrations

```shell
# To generate a migration
./vendor/bin/doctrine-migrations generate

# Run migrations
 ./vendor/bin/doctrine-migrations migrate --all-or-nothing
```

## Search (Elasticsearch)

Products are indexed into the `products` index whenever they are created, updated (including their rating) or deleted.
To (re)create the index from the database, e.g. on first setup or after Elasticsearch was unavailable:

```shell
docker exec app php bin/reindex-products.php
```

## Load testing with generated products

`bin/seed-products.php` generates random products (SKUs `SEED000000001`, ...) and ratings directly in Postgres, in batches.
An interrupted run resumes after the last seeded product when run again with the same `--products`.
Seeded products are not indexed automatically, so reindex afterwards:

```shell
# 100 million products (default), up to 10 ratings each (about 2.5 on average)
docker exec app php bin/seed-products.php --products=100000000 --max-ratings=10
docker exec app php bin/reindex-products.php

# Remove the generated products (their ratings are deleted in cascade), then reindex
docker exec postgres psql -U app -d app -c "DELETE FROM products WHERE sku > 'SEED' AND sku < 'SEEE'"
```

Measured locally (12 CPUs, Docker Desktop): seeding ~16k products/s (~1h45 for 100M) and indexing ~32k products/s (~52 min for 100M).
Expect roughly 90 GB in Postgres and 21 GB in Elasticsearch for 100M products.

