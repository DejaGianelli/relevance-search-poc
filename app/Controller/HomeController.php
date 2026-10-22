<?php
namespace App\Controller;

use App\Entity\Product;
use App\Entity\ProductRating;
use App\Search\ProductIndex;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment;

class HomeController extends AbstractController {

    public function __construct(Environment $twig, private EntityManagerInterface $em, private ProductIndex $productIndex) {
        parent::__construct($twig);
    }

    private const PER_PAGE = 12;

    public function showHome() {
        $query = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';

        if ($query === '') {
            [$products, $pagination] = $this->paginate(
                $this->em->getRepository(Product::class), ['active' => true], ['createdAt' => 'DESC', 'id' => 'DESC'], self::PER_PAGE,
            );
        } else {
            [$products, $pagination] = $this->search($query);
        }

        return $this->render('index', [
            'query' => $query,
            'products' => $products,
            'my_ratings' => $this->em->getRepository(ProductRating::class)->starsByRater(session_id(), $products),
            'pagination' => $pagination,
        ]);
    }

    /**
     * Searches Elasticsearch and loads the matching products from the database, keeping the relevance order.
     */
    private function search(string $query): array {
        // Elasticsearch only pages through the first ProductIndex::MAX_RESULT_WINDOW hits
        $page = min($this->requestedPage(), (int) ceil(ProductIndex::MAX_RESULT_WINDOW / self::PER_PAGE));
        $result = $this->productIndex->search($query, ($page - 1) * self::PER_PAGE, self::PER_PAGE);

        $found = $result['ids'] ? $this->em->getRepository(Product::class)->findBy(['id' => $result['ids']]) : [];
        $byId = [];
        foreach ($found as $product) {
            $byId[$product->getId()->toRfc4122()] = $product;
        }
        // Skips products removed from the database but still in the index
        $products = array_values(array_filter(array_map(fn($id) => $byId[$id] ?? null, $result['ids'])));

        $pagination = $this->pagination($page, self::PER_PAGE, $result['total'], count($products));
        $pagination['capped'] = $result['capped'];

        return [$products, $pagination];
    }
}
