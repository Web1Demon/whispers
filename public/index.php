<?php

declare(strict_types=1);

// Built-in PHP CLI Web Server static file handler
if (php_sapi_name() === 'cli-server') {
    $uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');
    $file = __DIR__ . $uri;
    if ($uri !== '/' && file_exists($file) && is_file($file)) {
        return false; // Serve static file directly
    }
}

// Whisper Application Front Controller
require_once __DIR__ . '/../app/Core/Autoloader.php';

$autoloader = new \App\Core\Autoloader('App', __DIR__ . '/../app');
$autoloader->register();

// Load configurations
$appConfig = require __DIR__ . '/../config/app.php';
$dbConfig = require __DIR__ . '/../config/database.php';

// Instantiate Application
$app = new \App\Core\Application($appConfig, $dbConfig);

// Auto-run schema migration & seed if database is uninitialized
try {
    /** @var \App\Infrastructure\Database\Migrator $migrator */
    $migrator = $app->getContainer()->get(\App\Infrastructure\Database\Migrator::class);
    $migrator->run();

    /** @var \App\Infrastructure\Database\DatabaseSeeder $seeder */
    $seeder = $app->getContainer()->get(\App\Infrastructure\Database\DatabaseSeeder::class);
    $seeder->seed();
} catch (\Throwable $e) {
    // Catch if already initialized
}

// Dispatch HTTP request
$app->run();
