<?php
// Shared setup of the web application (public/index.php) and the CLI scripts (bin/).
// Defines $basedir, $entityManager and $productIndex.

$basedir = dirname(__DIR__);

require $basedir . '/vendor/autoload.php';

use App\Config;
use App\Doctrine\UuidType;
use App\Search\ProductIndex;
use App\Search\ProductIndexListener;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Dotenv\Dotenv;
use Elastic\Elasticsearch\ClientBuilder;

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
$elasticsearchConfig = require $basedir . '/config/elasticsearch.php';

Config::init(['app' => $appConfig, 'db' => $dbConfig, 'elasticsearch' => $elasticsearchConfig]);

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

// Elasticsearch, kept in sync with the products saved through the ORM
$elasticsearch = ClientBuilder::create()->setHosts([Config::get('elasticsearch.host')])->build();
$productIndex = new ProductIndex($elasticsearch);
$entityManager->getEventManager()->addEventListener(
    [Events::postPersist, Events::postUpdate, Events::postRemove, Events::postFlush],
    new ProductIndexListener($productIndex),
);
