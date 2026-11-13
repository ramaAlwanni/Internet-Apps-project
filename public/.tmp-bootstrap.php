<?php

$start = microtime(true);

require __DIR__ . '/../vendor/autoload.php';
$autoload = microtime(true) - $start;

$appStart = microtime(true);
$app = require_once __DIR__ . '/../bootstrap/app.php';
$appTime = microtime(true) - $appStart;

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$bootstrapStart = microtime(true);
$kernel->bootstrap();
$bootstrapTime = microtime(true) - $bootstrapStart;

$handleStart = microtime(true);
$request = Illuminate\Http\Request::capture();
$response = $kernel->handle($request);
$handleTime = microtime(true) - $handleStart;

header('Content-Type: application/json');
echo json_encode([
    'autoload' => round($autoload, 3),
    'bootstrap_app' => round($appTime, 3),
    'kernel_bootstrap' => round($bootstrapTime, 3),
    'handle_after_bootstrap' => round($handleTime, 3),
    'total' => round(microtime(true) - $start, 3),
]);
