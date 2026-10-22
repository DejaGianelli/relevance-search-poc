<?php
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Controller\HomeController;
use App\Controller\ProductController;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

// Session (used for flash messages)
session_start();

// Configure Twig Engine
$loader = new FilesystemLoader($basedir . '/views');
$twig = new Environment($loader, []);

// Configure Routing
$routes = new RouteCollection();
$routes->add('home', new Route('/', ['_controller' => [HomeController::class, 'showHome']], methods: ['GET']));

$routes->add('products.index', new Route('/products', ['_controller' => [ProductController::class, 'index']], methods: ['GET']));
$routes->add('products.create', new Route('/products/new', ['_controller' => [ProductController::class, 'create']], methods: ['GET']));
$routes->add('products.store', new Route('/products', ['_controller' => [ProductController::class, 'store']], methods: ['POST']));
$routes->add('products.edit', new Route('/products/{id}/edit', ['_controller' => [ProductController::class, 'edit']], ['id' => Requirement::UUID], methods: ['GET']));
$routes->add('products.update', new Route('/products/{id}', ['_controller' => [ProductController::class, 'update']], ['id' => Requirement::UUID], methods: ['POST']));
$routes->add('products.rate', new Route('/products/{id}/rate', ['_controller' => [ProductController::class, 'rate']], ['id' => Requirement::UUID], methods: ['POST']));
$routes->add('products.delete', new Route('/products/{id}/delete', ['_controller' => [ProductController::class, 'delete']], ['id' => Requirement::UUID], methods: ['POST']));

// Controller factories
$controllers = [
    HomeController::class => fn() => new HomeController($twig, $entityManager, $productIndex),
    ProductController::class => fn() => new ProductController($twig, $entityManager),
];

$context = new RequestContext(method: $_SERVER['REQUEST_METHOD']);
$urlMatcher = new UrlMatcher($routes, $context);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Calling Specific Controller action from Routing
try {
    $parameters = $urlMatcher->match($path);
    [$controllerClass, $controllerMethod] = $parameters['_controller'];
    $controller = $controllers[$controllerClass]();

    // Route placeholders (e.g. {id}) are passed as named arguments
    $arguments = array_filter($parameters, fn($key) => !str_starts_with($key, '_'), ARRAY_FILTER_USE_KEY);

    echo $controller->$controllerMethod(...$arguments);
} catch (ResourceNotFoundException) {
    http_response_code(404);
    echo 'Not Found';
} catch (MethodNotAllowedException) {
    http_response_code(405);
    echo 'Method Not Allowed';
}
