<?php

namespace Modules\Order\Providers;

use InvalidArgumentException;
use Modules\Order\Contracts\StockManager;
use Modules\Order\Services\GrpcStockManager;
use Modules\Order\Services\LocalStockManager;
use Nwidart\Modules\Support\ModuleServiceProvider;

class OrderServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Order';

    protected string $nameLower = 'order';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        // Resolve the configured driver lazily. Nwidart merges module config
        // inside boot(), which runs after register(), so reading the config
        // eagerly here would always fall back to the default value.
        $this->app->bind(StockManager::class, function ($app): StockManager {
            return match (config('order.stock_manager', 'local')) {
                'local' => $app->make(LocalStockManager::class),
                'grpc' => $app->make(GrpcStockManager::class),
                default => throw new InvalidArgumentException(
                    'ORDER_STOCK_MANAGER must be either "local" or "grpc".'
                ),
            };
        });
    }

    public function boot(): void
    {
        parent::boot();
    }
}
