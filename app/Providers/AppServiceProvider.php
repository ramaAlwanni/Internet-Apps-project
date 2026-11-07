<?php

namespace App\Providers;

use App\Contracts\Inventory\InventoryGateway;
use App\Policies\OrderPolicy;
use App\Services\Inventory\GrpcInventoryGateway;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Order\Models\Order;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(InventoryGateway::class, GrpcInventoryGateway::class);
    }

    public function boot(): void
    {
        Gate::policy(
            Order::class,
            OrderPolicy::class
        );
    }
}
