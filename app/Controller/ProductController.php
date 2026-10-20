<?php
namespace App\Controller;

use App\Entity\Product;
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
        $repository = $this->em->getRepository(Product::class);

        $total = $repository->count([]);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $page = min($page ?: 1, $pages);

        // id (UUID v7) breaks ties between products created in the same second
        $products = $repository->findBy([], ['createdAt' => 'DESC', 'id' => 'DESC'], self::PER_PAGE, ($page - 1) * self::PER_PAGE);

        return $this->render('products/index', [
            'products' => $products,
            'pagination' => [
                'page' => $page,
                'pages' => $pages,
                'total' => $total,
                'from' => $total ? ($page - 1) * self::PER_PAGE + 1 : 0,
                'to' => ($page - 1) * self::PER_PAGE + count($products),
            ],
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
