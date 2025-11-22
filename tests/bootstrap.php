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

// Only setup test database if running in test environment and using SQLite
// This prevents accidental destruction of production database
$testDbPath = dirname(__DIR__) . '/var/test.db';

// Remove old test database and recreate schema only for SQLite test database
if (file_exists($testDbPath)) {
    @unlink($testDbPath);
}

// Create fresh test database schema - ONLY for test environment with SQLite
passthru('APP_ENV=test php ' . dirname(__DIR__) . '/bin/console doctrine:schema:create --no-interaction --quiet 2>/dev/null');
