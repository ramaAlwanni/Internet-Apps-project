<?php

namespace Modules\Order\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Order\app\Services\GrpcStockManager;
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
        // replace the binding of StockManager with GrpcStockManager instead of LocalStockManager
        // $this->app->bind(StockManager::class,LocalStockManager::class);
        $this->app->bind(StockManager::class, GrpcStockManager::class );
    }

    public function boot(): void
    {
        parent::boot();
    }
}
