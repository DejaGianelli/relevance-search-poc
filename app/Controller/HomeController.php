<?php
namespace App\Controller;

class HomeController extends AbstractController {

    public function showHome() {
        return $this->render('index', ['hello' => 'Hello', 'world' => 'World']);
    }
}
