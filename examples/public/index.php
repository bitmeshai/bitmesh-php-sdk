<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

$projectRoot = dirname(__DIR__);
$envFile = $projectRoot . '/.env';
if (is_file($envFile) && is_readable($envFile)) {
    $dotenv = Dotenv\Dotenv::createImmutable($projectRoot);
    $dotenv->safeLoad();
}

use BitmeshExample\Router;

$path = $_SERVER['REQUEST_URI'] ?? '/';
(new Router())->dispatch($path);
