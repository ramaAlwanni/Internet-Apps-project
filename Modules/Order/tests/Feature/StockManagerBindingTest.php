<?php

namespace Modules\Order\Tests\Feature;

use InvalidArgumentException;
use Modules\Order\Contracts\StockManager;
use Modules\Order\Services\GrpcStockManager;
use Modules\Order\Services\LocalStockManager;
use Tests\TestCase;

class StockManagerBindingTest extends TestCase
{
    public function test_it_resolves_the_local_driver_from_config(): void
    {
        config(['order.stock_manager' => 'local']);

        $this->assertInstanceOf(
            LocalStockManager::class,
            $this->app->make(StockManager::class)
        );
    }

    public function test_it_resolves_the_grpc_driver_from_config(): void
    {
        config(['order.stock_manager' => 'grpc']);

        $this->assertInstanceOf(
            GrpcStockManager::class,
            $this->app->make(StockManager::class)
        );
    }

    public function test_it_reads_the_configured_driver_at_resolution_time(): void
    {
        config(['order.stock_manager' => 'local']);
        $this->assertInstanceOf(
            LocalStockManager::class,
            $this->app->make(StockManager::class)
        );

        config(['order.stock_manager' => 'grpc']);
        $this->assertInstanceOf(
            GrpcStockManager::class,
            $this->app->make(StockManager::class)
        );
    }

    public function test_it_rejects_an_unknown_driver(): void
    {
        config(['order.stock_manager' => 'redis']);

        $this->expectException(InvalidArgumentException::class);

        $this->app->make(StockManager::class);
    }
}
