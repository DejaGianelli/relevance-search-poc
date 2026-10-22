<?php
$basedir = dirname(__DIR__);

require $basedir . '/vendor/autoload.php';

use App\Config;
use App\Controller\HomeController;
use App\Controller\ProductController;
use App\Doctrine\UuidType;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Dotenv\Dotenv;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

// Register Autoloader for application files following PSR-4
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = dirname(__DIR__) . '/app/';
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
require $basedir . '/app/functions.php';

// Load Configuration
$dotenv = Dotenv::createImmutable($basedir);
$dotenv->load();

$appConfig = require $basedir . '/config/app.php';
$dbConfig = require $basedir . '/config/database.php';

Config::init(['app' => $appConfig, 'db' => $dbConfig]);

// Database
$dsnParser = new DsnParser(['postgresql' => 'pdo_pgsql']);
$connParams = $dsnParser->parse(Config::get('db.dsn'));
$connection = DriverManager::getConnection($connParams);

// ORM
Type::addType(UuidType::NAME, UuidType::class);
$ormConfig = ORMSetup::createAttributeMetadataConfiguration(
    paths: [$basedir . '/app/Entity'],
    isDevMode: Config::get('app.env') === 'dev',
    proxyDir: $basedir . '/cache/doctrine/proxies',
);
$entityManager = new EntityManager($connection, $ormConfig);

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
    HomeController::class => fn() => new HomeController($twig, $entityManager),
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
