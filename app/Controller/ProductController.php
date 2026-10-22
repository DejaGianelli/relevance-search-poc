<?php
namespace App\Controller;

use App\Entity\Product;
use App\Entity\ProductRating;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Uid\Uuid;
use Twig\Environment;

class ProductController extends AbstractController {

    public function __construct(Environment $twig, private EntityManagerInterface $em) {
        parent::__construct($twig);
    }

    private const PER_PAGE = 20;

    public function index() {
        // id (UUID v7) breaks ties between products created in the same second
        [$products, $pagination] = $this->paginate(
            $this->em->getRepository(Product::class), [], ['createdAt' => 'DESC', 'id' => 'DESC'], self::PER_PAGE,
        );

        return $this->render('products/index', [
            'products' => $products,
            'pagination' => $pagination,
        ]);
    }

    public function create() {
        return $this->renderForm(null, [
            'name' => '', 'sku' => '', 'description' => '', 'price' => '', 'stock' => '0', 'active' => true,
        ]);
    }

    public function store() {
        [$data, $errors] = $this->validate($_POST);
        if ($errors) {
            return $this->renderForm(null, $_POST + ['active' => false], $errors);
        }

        $product = new Product($data['name'], $data['sku'], $data['price'], $data['stock'], $data['description'], $data['active']);
        $this->em->persist($product);
        $this->em->flush();

        $this->flash('success', 'Product created.');
        return $this->redirect('/products');
    }

    public function edit(string $id) {
        $product = $this->find($id);
        return $this->renderForm($product, [
            'name' => $product->getName(),
            'sku' => $product->getSku(),
            'description' => $product->getDescription() ?? '',
            'price' => $product->getPrice(),
            'stock' => (string) $product->getStock(),
            'active' => $product->isActive(),
        ]);
    }

    public function update(string $id) {
        $product = $this->find($id);
        [$data, $errors] = $this->validate($_POST, $product);
        if ($errors) {
            return $this->renderForm($product, $_POST + ['active' => false], $errors);
        }

        $product->update($data['name'], $data['sku'], $data['price'], $data['stock'], $data['description'], $data['active']);
        $this->em->flush();

        $this->flash('success', 'Product updated.');
        return $this->redirect('/products');
    }

    public function delete(string $id) {
        $product = $this->find($id);
        $this->em->remove($product);
        $this->em->flush();

        $this->flash('success', 'Product deleted.');
        return $this->redirect('/products');
    }

    public function rate(string $id) {
        $product = $this->find($id);
        $stars = filter_var($_POST['stars'] ?? '', FILTER_VALIDATE_INT, [
            'options' => ['min_range' => ProductRating::MIN_STARS, 'max_range' => ProductRating::MAX_STARS],
        ]);
        if ($stars === false || !$product->isActive()) {
            http_response_code(422);
            return 'Invalid rating.';
        }

        // Visitors are anonymous, so each session holds one rating per product, which can be changed
        $rater = session_id();
        $this->em->wrapInTransaction(function () use ($product, $rater, $stars) {
            // Serializes concurrent ratings of the same product, so the summary always includes all of them
            $this->em->lock($product, LockMode::PESSIMISTIC_WRITE);

            $ratings = $this->em->getRepository(ProductRating::class);
            $rating = $ratings->findOneBy(['product' => $product, 'rater' => $rater]);
            if ($rating) {
                $rating->rate($stars);
            } else {
                $this->em->persist(new ProductRating($product, $rater, $stars));
            }
            $this->em->flush();

            $summary = $ratings->summary($product);
            $product->updateRatingSummary($summary['average'], $summary['count']);
        });

        // Back to the same search and page of the home, scrolled to the rated product
        $query = array_filter([
            'q' => is_string($_POST['q'] ?? null) ? trim($_POST['q']) : '',
            'page' => filter_var($_POST['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1,
        ]);
        return $this->redirect('/?' . http_build_query($query) . '#product-' . $product->getId());
    }

    private function renderForm(?Product $product, array $values, array $errors = []): string {
        return $this->render('products/form', [
            'product' => $product,
            'values' => $values,
            'errors' => $errors,
        ], $errors ? 422 : 200);
    }

    private function find(string $id): Product {
        $product = Uuid::isValid($id) ? $this->em->find(Product::class, Uuid::fromString($id)) : null;
        if (!$product) {
            throw new ResourceNotFoundException("Product $id not found.");
        }
        return $product;
    }

    /**
     * @return array{0: array, 1: array<string, string>} normalized data and errors by field
     */
    private function validate(array $input, ?Product $current = null): array {
        $errors = [];

        // Discard non-string and invalid UTF-8 values so they fail validation instead of breaking the database
        $input = array_map(fn($v) => is_string($v) && mb_check_encoding($v, 'UTF-8') ? $v : '', $input);

        $name = trim($input['name'] ?? '');
        if ($name === '') {
            $errors['name'] = 'Name is required.';
        } elseif (mb_strlen($name) > 255) {
            $errors['name'] = 'Name must have at most 255 characters.';
        }

        $sku = strtoupper(trim($input['sku'] ?? ''));
        if ($sku === '') {
            $errors['sku'] = 'SKU is required.';
        } elseif (!preg_match('/^[A-Z0-9_-]{1,64}$/', $sku)) {
            $errors['sku'] = 'SKU must have up to 64 letters, numbers, "-" or "_".';
        } else {
            $existing = $this->em->getRepository(Product::class)->findOneBy(['sku' => $sku]);
            if ($existing && $existing !== $current) {
                $errors['sku'] = 'SKU already in use.';
            }
        }

        $price = str_replace(',', '.', trim($input['price'] ?? ''));
        if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $price)) {
            $errors['price'] = 'Price must be a positive number with up to 2 decimals.';
        }

        $stock = filter_var($input['stock'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($stock === false) {
            $errors['stock'] = 'Stock must be an integer greater than or equal to 0.';
        }

        $description = trim($input['description'] ?? '');

        $data = [
            'name' => $name,
            'sku' => $sku,
            'description' => $description === '' ? null : $description,
            'price' => $price,
            'stock' => (int) $stock,
            'active' => isset($input['active']),
        ];

        return [$data, $errors];
    }
}
