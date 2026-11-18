<?php

namespace App\Providers;

use App\Contracts\Inventory\InventoryGateway;
use App\Policies\OrderPolicy;
use App\Services\Inventory\GrpcInventoryGateway;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Order\Models\Order;
use Modules\User\Models\User;

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

        /*
         * Admins are trusted with the whole back office. This keeps the
         * `permission:*` route middleware working even when the Spatie
         * permission cache in a fresh/rolled-back database is out of sync
         * with config/permissions.php (which previously produced
         * "User does not have the right permissions." for admins).
         */
        Gate::before(function (User $user) {
            return $user->hasRole('admin') ? true : null;
        });
    }
}
