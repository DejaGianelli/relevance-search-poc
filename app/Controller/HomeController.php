<?php
namespace App\Controller;

use Twig\Environment;

class HomeController {

    public function __construct(private Environment $twig) {}

    public function showHome() {
        return $this->render('index', ['hello' => 'Hello', 'world' => 'World']);
    }

    private function render(string $view, array $data = []) {
        $template = $this->twig->load($view . '.html.twig');
        return $template->render($data);
    }
}