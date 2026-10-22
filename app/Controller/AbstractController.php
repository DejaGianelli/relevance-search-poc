<?php
namespace App\Controller;

use Doctrine\ORM\EntityRepository;
use Twig\Environment;

abstract class AbstractController {

    public function __construct(protected Environment $twig) {}

    protected function render(string $view, array $data = [], int $status = 200): string {
        http_response_code($status);
        $data['flashes'] = $this->consumeFlashes();
        $template = $this->twig->load($view . '.html.twig');
        return $template->render($data);
    }

    protected function redirect(string $url): string {
        header('Location: ' . $url, true, 303);
        return '';
    }

    protected function flash(string $type, string $message): void {
        $_SESSION['flashes'][] = ['type' => $type, 'message' => $message];
    }

    /**
     * Fetches the page requested in $_GET['page'] (clamped to the existing pages).
     *
     * @return array{0: array, 1: array{page: int, pages: int, total: int, from: int, to: int}} items and pagination info
     */
    protected function paginate(EntityRepository $repository, array $criteria, array $orderBy, int $perPage): array {
        $total = $repository->count($criteria);
        $page = min($this->requestedPage(), max(1, (int) ceil($total / $perPage)));

        $items = $repository->findBy($criteria, $orderBy, $perPage, ($page - 1) * $perPage);

        return [$items, $this->pagination($page, $perPage, $total, count($items))];
    }

    protected function requestedPage(): int {
        return filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
    }

    /**
     * @return array{page: int, pages: int, total: int, from: int, to: int}
     */
    protected function pagination(int $page, int $perPage, int $total, int $count): array {
        return [
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $perPage)),
            'total' => $total,
            'from' => $count ? ($page - 1) * $perPage + 1 : 0,
            'to' => ($page - 1) * $perPage + $count,
        ];
    }

    private function consumeFlashes(): array {
        $flashes = $_SESSION['flashes'] ?? [];
        unset($_SESSION['flashes']);
        return $flashes;
    }
}
