<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Config;
use App\Controller\HomeController;
use Dotenv\Dotenv;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

// Register Autoloader for application files following PSR-4
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

// Reserved global functions
require __DIR__ . '/functions.php';

// Load Configuration
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$appConfig = require __DIR__ . '/config/app.php';

Config::init(['app' => $appConfig]);

// Configure Twig Engine
$loader = new FilesystemLoader(__DIR__ . '/views');
$twig = new Environment($loader, []);

// Configure Routing
$home = new Route('/', [
    '_controller' => [HomeController::class, 'showHome'],
    '_methods' => 'GET'
]);
$routes = new RouteCollection();
$routes->add('home', $home);
$context = new RequestContext();
$urlMatcher = new UrlMatcher($routes, $context);
$requestUri = $_SERVER['REQUEST_URI'];
$parameters = $urlMatcher->match($requestUri);

// Calling Specific Controller action from Routing
$controllerClass = $parameters['_controller'][0];
$controllerMethod = $parameters['_controller'][1];
$controller = new $controllerClass($twig);

if (method_exists($controller, $controllerMethod) && is_callable([$controller, $controllerMethod])) {
    $response = $controller->$controllerMethod();
    echo $response;
}
