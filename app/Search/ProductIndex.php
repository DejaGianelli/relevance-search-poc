<?php

namespace App\Search;

use App\Entity\Product;
use Elastic\Elasticsearch\Client;

/**
 * The Elasticsearch "products" index, used to search products by name and description.
 */
class ProductIndex
{
    public const NAME = 'products';

    // Elasticsearch's index.max_result_window: hits beyond it can't be paged with from/size
    public const MAX_RESULT_WINDOW = 10000;

    public function __construct(private Client $client) {}

    /**
     * Drops the index (if it exists) and creates it again, empty.
     */
    public function recreate(): void
    {
        if ($this->client->indices()->exists(['index' => self::NAME])->asBool()) {
            $this->client->indices()->delete(['index' => self::NAME]);
        }

        $this->client->indices()->create([
            'index' => self::NAME,
            'body' => [
                'settings' => [
                    // Single node cluster: replicas would never be allocated
                    'number_of_replicas' => 0,
                    'analysis' => [
                        'analyzer' => [
                            // Language neutral, but matches "descricao" with "Descrição"
                            'folding' => [
                                'type' => 'custom',
                                'tokenizer' => 'standard',
                                'filter' => ['lowercase', 'asciifolding'],
                            ],
                        ],
                    ],
                ],
                'mappings' => [
                    'dynamic' => 'strict',
                    'properties' => [
                        'name' => ['type' => 'text', 'analyzer' => 'folding'],
                        'sku' => ['type' => 'keyword'],
                        'description' => ['type' => 'text', 'analyzer' => 'folding'],
                        'price' => ['type' => 'scaled_float', 'scaling_factor' => 100],
                        'stock' => ['type' => 'integer'],
                        'active' => ['type' => 'boolean'],
                        'rating_average' => ['type' => 'float'],
                        'rating_count' => ['type' => 'integer'],
                        'created_at' => ['type' => 'date'],
                        'updated_at' => ['type' => 'date'],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Disables the periodic refresh, which makes indexing large amounts of documents much faster.
     * Documents indexed meanwhile are only searchable after endBulkLoad().
     */
    public function beginBulkLoad(): void
    {
        $this->client->indices()->putSettings(['index' => self::NAME, 'body' => ['index' => ['refresh_interval' => '-1']]]);
    }

    public function endBulkLoad(): void
    {
        $this->client->indices()->putSettings(['index' => self::NAME, 'body' => ['index' => ['refresh_interval' => null]]]);
        $this->client->indices()->refresh(['index' => self::NAME]);
    }

    /**
     * Indexes products straight from database rows (with the products table columns), skipping the ORM.
     *
     * @param array<array<string, mixed>> $rows
     */
    public function indexRows(array $rows): void
    {
        $body = [];
        foreach ($rows as $row) {
            $body[] = ['index' => ['_index' => self::NAME, '_id' => $row['id']]];
            $body[] = $this->document($row);
        }
        $this->bulk($body);
    }

    /**
     * Indexes (creating or replacing) and deletes products in a single bulk request.
     *
     * @param Product[] $save
     * @param string[] $delete ids of the products to remove from the index
     * @throws \RuntimeException when Elasticsearch rejects any of the operations
     */
    public function sync(array $save, array $delete = []): void
    {
        $body = [];
        foreach ($save as $product) {
            $body[] = ['index' => ['_index' => self::NAME, '_id' => $product->getId()->toRfc4122()]];
            $body[] = $this->document($this->row($product));
        }
        foreach ($delete as $id) {
            $body[] = ['delete' => ['_index' => self::NAME, '_id' => $id]];
        }
        $this->bulk($body);
    }

    /**
     * @throws \RuntimeException when Elasticsearch rejects any of the operations
     */
    private function bulk(array $body): void
    {
        if (!$body) {
            return;
        }

        $response = $this->client->bulk(['body' => $body])->asArray();
        if ($response['errors']) {
            // Deleting a product that was never indexed is not a failure
            $failures = array_filter(array_map(
                fn($item) => current($item)['status'] === 404 ? null : current($item)['error'] ?? null,
                $response['items'],
            ));
            if ($failures) {
                throw new \RuntimeException('Elasticsearch bulk failed: ' . json_encode(array_values($failures)));
            }
        }
    }

    /**
     * Full text search on the name and description of active products, a match in the name weighting
     * 3 times a match in the description. The text relevance is multiplied
     * by a factor that grows with the average rating, so better rated products rank higher.
     *
     * Only the first MAX_RESULT_WINDOW hits can be fetched, and matches are counted up to that number.
     *
     * @return array{ids: string[], total: int, capped: bool} ids ordered by relevance, the total of
     *     matches and whether there are more matches than the total
     */
    public function search(string $query, int $from, int $size): array
    {
        $size = max(0, min($size, self::MAX_RESULT_WINDOW - $from));
        if ($size === 0) {
            return ['ids' => [], 'total' => 0, 'capped' => false];
        }

        $response = $this->client->search([
            'index' => self::NAME,
            'body' => [
                'from' => $from,
                'size' => $size,
                'track_total_hits' => self::MAX_RESULT_WINDOW,
                '_source' => false,
                'query' => [
                    'function_score' => [
                        'query' => [
                            'bool' => [
                                'must' => [
                                    'multi_match' => [
                                        'query' => $query,
                                        'fields' => ['name^3', 'description'],
                                        // Scores by the best matching field, plus 30% of the other one
                                        'type' => 'best_fields',
                                        'tie_breaker' => 0.3,
                                    ],
                                ],
                                'filter' => ['term' => ['active' => true]],
                            ],
                        ],
                        // log10(2 + average): 0.30 without ratings, 0.48 for 1 star, 0.85 for 5 stars
                        'functions' => [
                            ['field_value_factor' => ['field' => 'rating_average', 'modifier' => 'log2p', 'missing' => 0]],
                        ],
                        'boost_mode' => 'multiply',
                    ],
                ],
            ],
        ])->asArray();

        return [
            'ids' => array_column($response['hits']['hits'], '_id'),
            'total' => $response['hits']['total']['value'],
            'capped' => $response['hits']['total']['relation'] === 'gte',
        ];
    }

    /**
     * The product as a row of the products table, so entities and rows share document().
     */
    private function row(Product $product): array
    {
        return [
            'name' => $product->getName(),
            'sku' => $product->getSku(),
            'description' => $product->getDescription(),
            'price' => $product->getPrice(),
            'stock' => $product->getStock(),
            'active' => $product->isActive(),
            'rating_average' => $product->getRatingAverage(),
            'rating_count' => $product->getRatingCount(),
            'created_at' => $product->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $product->getUpdatedAt()->format('Y-m-d H:i:s'),
        ];
    }

    private function document(array $row): array
    {
        return [
            'name' => $row['name'],
            'sku' => $row['sku'],
            'description' => $row['description'],
            'price' => (float) $row['price'],
            'stock' => (int) $row['stock'],
            'active' => (bool) $row['active'],
            'rating_average' => $row['rating_average'] === null ? null : (float) $row['rating_average'],
            'rating_count' => (int) $row['rating_count'],
            // Timestamps are stored without time zone, in UTC
            'created_at' => str_replace(' ', 'T', $row['created_at']),
            'updated_at' => str_replace(' ', 'T', $row['updated_at']),
        ];
    }
}
