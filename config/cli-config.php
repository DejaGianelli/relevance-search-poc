<?php
$root = dirname(__DIR__);

require $root . '/vendor/autoload.php';
require $root . '/app/Config.php';
require $root . '/app/functions.php';

use App\Config;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\PhpFile;
use Doctrine\Migrations\DependencyFactory;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable($root);
$dotenv->load();

$dbConfig = require $root . '/config/database.php';

Config::init(['db' => $dbConfig]);
$dsnParser = new DsnParser(['postgresql' => 'pdo_pgsql']);
$connParams = $dsnParser->parse(Config::get('db.dsn'));
$conn = DriverManager::getConnection($connParams);

return DependencyFactory::fromConnection(
    new PhpFile($root . '/config/migrations.php'),
    new ExistingConnection($conn)
);
