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