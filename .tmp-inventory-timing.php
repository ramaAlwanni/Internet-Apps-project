<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$gateway = app(App\Contracts\Inventory\InventoryGateway::class);
$ids = Modules\Product\Models\Product::query()->limit(4)->pluck('id')->all();

$start = microtime(true);
try {
    $gateway->getAvailableQuantity((int) $ids[0]);
    echo 'single rpc: ok' . PHP_EOL;
} catch (Throwable $exception) {
    echo 'single rpc failed in ' . round(microtime(true) - $start, 2) . ' s: '
        . $exception->getMessage() . PHP_EOL;
}

$start = microtime(true);
try {
    app(App\Contracts\Catalog\ProductCatalog::class)->findByIds($ids);
    echo 'findByIds: ok' . PHP_EOL;
} catch (Throwable $exception) {
    echo 'findByIds failed in ' . round(microtime(true) - $start, 2) . ' s: '
        . $exception->getMessage() . PHP_EOL;
}

$start = microtime(true);
try {
    app(App\Contracts\Catalog\ProductCatalog::class)->findByIds($ids);
    echo 'findByIds again: ok' . PHP_EOL;
} catch (Throwable $exception) {
    echo 'findByIds again failed in ' . round(microtime(true) - $start, 2) . ' s: '
        . $exception->getMessage() . PHP_EOL;
}
