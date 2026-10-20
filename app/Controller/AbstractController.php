<?php
namespace App\Controller;

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

    private function consumeFlashes(): array {
        $flashes = $_SESSION['flashes'] ?? [];
        unset($_SESSION['flashes']);
        return $flashes;
    }
}
