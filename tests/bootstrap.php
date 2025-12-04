<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (file_exists(dirname(__DIR__).'/config/bootstrap.php')) {
    require dirname(__DIR__).'/config/bootstrap.php';
} elseif (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

// IMPORTANT: Force test environment to prevent accidental production data loss
$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';

// Drop and recreate test database schema
passthru('APP_ENV=test php ' . dirname(__DIR__) . '/bin/console doctrine:database:drop --force --if-exists --no-interaction --quiet 2>/dev/null');
passthru('APP_ENV=test php ' . dirname(__DIR__) . '/bin/console doctrine:database:create --no-interaction --quiet 2>/dev/null');
passthru('APP_ENV=test php ' . dirname(__DIR__) . '/bin/console doctrine:schema:create --no-interaction --quiet 2>/dev/null');
