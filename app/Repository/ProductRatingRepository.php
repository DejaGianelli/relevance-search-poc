<?php

namespace App\Repository;

use App\Entity\Product;
use App\Entity\ProductRating;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<ProductRating>
 */
class ProductRatingRepository extends EntityRepository
{
    /**
     * Average stars (rounded to 2 decimals) and number of ratings of a product.
     * Only scans the ratings of that product, through the (product_id, rater) unique index.
     *
     * @return array{average: ?string, count: int} average is null when the product has no ratings
     */
    public function summary(Product $product): array
    {
        $row = $this->createQueryBuilder('r')
            ->select('AVG(r.stars) AS average', 'COUNT(r.id) AS total')
            ->where('r.product = :product')
            ->setParameter('product', $product->getId()->toRfc4122())
            ->getQuery()
            ->getSingleResult();

        return [
            'average' => $row['total'] ? number_format((float) $row['average'], 2, '.', '') : null,
            'count' => (int) $row['total'],
        ];
    }

    /**
     * Stars the rater gave to each of the given products.
     *
     * @param Product[] $products
     * @return array<string, int> keyed by product id; products not rated by the rater are omitted
     */
    public function starsByRater(string $rater, array $products): array
    {
        if (!$products) {
            return [];
        }

        $rows = $this->createQueryBuilder('r')
            ->select('IDENTITY(r.product) AS product', 'r.stars')
            ->where('r.rater = :rater')
            ->andWhere('r.product IN (:products)')
            ->setParameter('rater', $rater)
            ->setParameter('products', array_map(fn(Product $p) => $p->getId()->toRfc4122(), $products))
            ->getQuery()
            ->getArrayResult();

        return array_column($rows, 'stars', 'product');
    }
}
