<?php

declare(strict_types=1);

$requiredEnvironment = [
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_URL' => '',
    'ORDER_STOCK_MANAGER' => 'local',
];

foreach ($requiredEnvironment as $name => $expectedValue) {
    if (getenv($name) !== $expectedValue) {
        fwrite(STDERR, "Refusing to run tests: {$name} is not safely configured.\n");
        exit(1);
    }

    $_ENV[$name] = $expectedValue;
    $_SERVER[$name] = $expectedValue;
}

$configCachePath = 'bootstrap/cache/config.testing.php';
putenv('APP_CONFIG_CACHE=' . $configCachePath);
$_ENV['APP_CONFIG_CACHE'] = $configCachePath;
$_SERVER['APP_CONFIG_CACHE'] = $configCachePath;

if (!extension_loaded('pdo_sqlite') || !in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    fwrite(STDERR, "Refusing to run tests: the PDO SQLite extension is required.\n");
    exit(1);
}

$configCache = dirname(__DIR__) . '/bootstrap/cache/config.testing.php';
if (is_file($configCache)) {
    fwrite(STDERR, "Refusing to run tests: the isolated test configuration cache already exists.\n");
    exit(1);
}

require dirname(__DIR__) . '/vendor/autoload.php';
