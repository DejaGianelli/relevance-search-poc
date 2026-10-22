<?php
// Generates random products (SKUs SEED000000001, SEED000000002, ...) with random ratings, for load testing.
// Rows are generated inside Postgres in batches, so millions of products take minutes, not hours.
// Interrupted runs resume after the last seeded product. Products are not indexed: run
// bin/reindex-products.php afterwards.
//
// Usage: php bin/seed-products.php [--products=100000000] [--batch=100000] [--max-ratings=10]
//   --products     total number of seeded products to reach
//   --batch        products inserted per statement (and transaction)
//   --max-ratings  max ratings per product; counts are skewed towards 0 (about max / 4 on average)

require dirname(__DIR__) . '/app/bootstrap.php';

$options = getopt('', ['products:', 'batch:', 'max-ratings:']) + ['products' => 100_000_000, 'batch' => 100_000, 'max-ratings' => 10];
$target = (int) $options['products'];
$batch = (int) $options['batch'];
$maxRatings = (int) $options['max-ratings'];
if ($target < 1 || $batch < 1 || $maxRatings < 0 || $target > 999_999_999) {
    fwrite(STDERR, "Invalid options.\n");
    exit(1);
}

$types = [
    'Cafeteira', 'Moedor de café', 'Chaleira', 'Liquidificador', 'Batedeira', 'Torradeira', 'Panela de pressão', 'Frigideira',
    'Jogo de facas', 'Garrafa térmica', 'Air fryer', 'Micro-ondas', 'Aspirador de pó', 'Ventilador', 'Ferro de passar',
    'Secador de cabelo', 'Notebook', 'Smartphone', 'Tablet', 'Fone de ouvido', 'Caixa de som', 'Teclado', 'Mouse', 'Monitor',
    'Webcam', 'Smartwatch', 'Carregador', 'Cadeira de escritório', 'Mesa de jantar', 'Sofá', 'Luminária', 'Tapete',
    'Travesseiro', 'Edredom', 'Tênis de corrida', 'Camiseta', 'Jaqueta', 'Mochila', 'Mala de viagem', 'Relógio', 'Óculos de sol',
    'Bicicleta', 'Barraca de camping', 'Bola de futebol', 'Halteres', 'Tapete de yoga', 'Furadeira', 'Parafusadeira',
    'Kit de ferramentas', 'Perfume', 'Hidratante', 'Escova de dentes elétrica', 'Brinquedo educativo', 'Quebra-cabeça',
];
$adjectives = [
    'Premium', 'Compacto', 'Portátil', 'Profissional', 'Elétrico', 'Automático', 'Digital', 'Ergonômico', 'Sem Fio', 'Gamer',
    'Ultra', 'Clássico', 'Esportivo', 'Inteligente', 'Básico', 'Deluxe', 'Slim', 'Turbo', 'Eco', 'Max',
];
$brands = ['Acme', 'Nova', 'Vértice', 'Solaris', 'Orion', 'Lumen', 'Kappa', 'Brisa', 'Atlas', 'Zênite', 'Aurora', 'Titan', 'Pampa', 'Ipê', 'Jade'];
$colors = ['Preto', 'Branco', 'Prata', 'Azul', 'Vermelho', 'Verde', 'Cinza', 'Rosa', 'Grafite', 'Dourado'];
$features = [
    'com acabamento em aço inox', 'ideal para uso diário', 'com bateria de longa duração', 'com garantia de 12 meses',
    'fácil de limpar', 'com design moderno', 'de alta resistência', 'leve e compacto', 'com conexão bluetooth',
    'feito com material reciclável', 'com potência de 1200W', 'com tela de alta resolução', 'perfeito para presentear',
    'extremamente silencioso', 'com baixo consumo de energia', 'à prova d\'água', 'com controle por aplicativo',
    'com ajuste de altura', 'com revestimento antiaderente', 'com alça reforçada', 'resistente a impactos',
    'com tecido respirável', 'com carregamento rápido', 'com cabo removível', 'com timer programável',
    'aprovado por especialistas', 'para toda a família', 'com várias velocidades', 'com sistema antiderrapante',
    'de fácil instalação', 'com acabamento fosco', 'com iluminação LED', 'com filtro permanente', 'para uso profissional',
];

$connection = $entityManager->getConnection();
$array = fn(array $words) => 'ARRAY[' . implode(', ', array_map(fn($w) => $connection->quote($w), $words)) . ']';
$pick = fn(string $words) => "words.{$words}[1 + floor(random() * cardinality(words.{$words}))::int]";
// UUID v7 (time ordered, as the app generates) from a timestamp: keeps primary key inserts sequential
$uuidV7 = fn(string $ts) => "encode(set_bit(set_bit(overlay(uuid_send(gen_random_uuid()) placing substring(int8send((extract(epoch FROM $ts) * 1000)::bigint) FROM 3) FROM 1 FOR 6), 52, 1), 53, 1), 'hex')::uuid";

// Products are spread over the last 3 years, in SKU order. Each one has a hidden "quality" its
// ratings gravitate around, so averages range from 1 to 5 instead of all converging to 3.
$sql = "
    WITH words AS (
        SELECT {$array($types)} AS types, {$array($adjectives)} AS adjectives, {$array($brands)} AS brands,
               {$array($colors)} AS colors, {$array($features)} AS features
    ),
    src AS MATERIALIZED (
        SELECT g.n, g.ts, {$uuidV7('g.ts')} AS id,
               {$pick('types')} AS type, {$pick('adjectives')} AS adjective, {$pick('brands')} AS brand, {$pick('colors')} AS color,
               {$pick('features')} AS f1, {$pick('features')} AS f2, {$pick('features')} AS f3,
               1 + random() * 4 AS quality,
               floor(power(random(), 3) * (CAST(:max_ratings AS int) + 1))::int AS ratings
        FROM (
            SELECT n, date_trunc('second', CAST(:since AS timestamp) + (n * CAST(:span AS float8) / CAST(:target AS float8)) * interval '1 second') AS ts
            FROM generate_series(CAST(:first AS int), CAST(:last AS int)) n
        ) g
        CROSS JOIN words
    ),
    r AS MATERIALIZED (
        SELECT src.id AS product_id, src.ts, i, least(5, greatest(1, round(src.quality + random() * 2 - 1)))::smallint AS stars
        FROM src CROSS JOIN LATERAL generate_series(1, src.ratings) i
    ),
    summary AS (
        SELECT product_id, round(avg(stars), 2) AS average, count(*) AS total FROM r GROUP BY product_id
    ),
    inserted AS (
        INSERT INTO products (id, name, sku, description, price, stock, active, rating_average, rating_count, created_at, updated_at)
        SELECT src.id,
               src.type || ' ' || src.adjective || ' ' || src.brand || ' ' || src.color,
               'SEED' || lpad(src.n::text, 9, '0'),
               src.type || ' ' || src.brand || ' ' || src.f1 || ', ' || src.f2 || ' e ' || src.f3 || '. Cor ' || lower(src.color) || '.',
               round((5 + power(random(), 2) * 4995)::numeric, 2),
               floor(random() * 500)::int,
               random() < 0.95,
               summary.average, coalesce(summary.total, 0), src.ts, src.ts
        FROM src LEFT JOIN summary ON summary.product_id = src.id
    )
    INSERT INTO product_ratings (id, product_id, rater, stars, created_at, updated_at)
    SELECT {$uuidV7('r.ts')}, r.product_id, 'seed-' || r.i, r.stars, r.ts, r.ts FROM r
";

// Resume after the last seeded product (the range lets the SKU unique index answer it)
$lastSku = $connection->fetchOne("SELECT sku FROM products WHERE sku > 'SEED' AND sku < 'SEEE' ORDER BY sku DESC LIMIT 1");
$done = $lastSku ? (int) substr($lastSku, 4) : 0;
if ($done >= $target) {
    echo "Already seeded $done products.\n";
    exit;
}

$since = $connection->fetchOne("SELECT date_trunc('day', now() - interval '3 years')::timestamp");
$span = 3 * 365 * 24 * 3600;

// Commits don't wait for the WAL flush: a crash loses at most the last batches, which a rerun redoes
$connection->executeStatement('SET synchronous_commit = off');

echo 'Seeding products ' . number_format($done + 1) . ' to ' . number_format($target) . "...\n";
$start = microtime(true);
for ($first = $done + 1; $first <= $target; $first += $batch) {
    $last = min($first + $batch - 1, $target);
    $ratings = $connection->executeStatement($sql, [
        'first' => $first, 'last' => $last, 'target' => $target, 'since' => $since, 'span' => $span, 'max_ratings' => $maxRatings,
    ]);

    $seeded = $last - $done;
    $rate = $seeded / (microtime(true) - $start);
    printf(
        "\r%s products (%s/s, ETA %s), last batch with %s ratings   ",
        number_format($last), number_format($rate), gmdate('H:i:s', (int) (($target - $last) / $rate)), number_format($ratings),
    );
}

// Refreshes the planner statistics (also used by bin/reindex-products.php for its progress)
echo "\nAnalyzing tables...";
$connection->executeStatement('ANALYZE products, product_ratings');

printf("\nSeeded %s products in %s. Now run: php bin/reindex-products.php\n", number_format($target - $done), gmdate('H:i:s', (int) (microtime(true) - $start)));
