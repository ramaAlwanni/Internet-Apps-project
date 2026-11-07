<?php

namespace Modules\Order\Providers;

use InvalidArgumentException;
use Modules\Order\Services\GrpcStockManager;
use Modules\Order\Contracts\StockManager;
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

        $stockManager = match (config('order.stock_manager', 'local')) {
            'local' => LocalStockManager::class,
            'grpc' => GrpcStockManager::class,
            default => throw new InvalidArgumentException(
                'ORDER_STOCK_MANAGER must be either "local" or "grpc".'
            ),
        };

        $this->app->bind(StockManager::class, $stockManager);
    }

    public function boot(): void
    {
        parent::boot();
    }
}
