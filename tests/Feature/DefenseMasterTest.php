<?php

namespace Tests\Feature;

use App\Contracts\Catalog\ProductCatalog;
use App\Contracts\Inventory\InventoryGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Order\Contracts\StockManager;
use Modules\Order\Models\Order;
use Modules\Order\Services\GrpcStockManager;
use Modules\Order\Services\LocalStockManager;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Modules\User\Models\User;
use RuntimeException;
use Tests\TestCase;

class DefenseMasterTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_cannot_change_an_order_status(): void
    {
        $customer = User::factory()->create();
        $order = $this->createOrderFor($customer);

        $this->actingAs($customer, 'api')
            ->putJson("/api/v1/orders/{$order->id}/status", [
                'status' => Order::STATUS_CONFIRMED,
            ])
            ->assertForbidden();

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
    }

    public function test_customer_cannot_view_another_users_order_or_list_it(): void
    {
        $customer = User::factory()->create();
        $anotherCustomer = User::factory()->create();
        $privateOrder = $this->createOrderFor($anotherCustomer);
        $customerOrder = $this->createOrderFor($customer);

        $this->actingAs($customer, 'api')
            ->getJson("/api/v1/orders/{$privateOrder->id}")
            ->assertNotFound();

        $this->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $customerOrder->id);
    }

    public function test_only_one_rapid_competing_purchase_can_claim_the_last_item(): void
    {
        // This exercises the real reservation and order flow against the
        // test database. Requests run one after another in-process; true
        // parallel locking requires a shared database/service integration test.
        $this->app->instance(
            StockManager::class,
            new LocalStockManager($this->app->make(ProductCatalog::class))
        );

        $product = $this->createProduct(stock: 1);
        $customers = User::factory()->count(8)->create();
        $responses = [];

        foreach ($customers as $customer) {
            $responses[] = $this->actingAs($customer, 'api')
                ->postJson('/api/v1/orders', [
                    'items' => [
                        [
                            'product_id' => $product->id,
                            'quantity' => 1,
                        ],
                    ],
                ]);
        }

        $successes = array_filter(
            $responses,
            fn ($response) => $response->getStatusCode() === 201
        );
        $stockFailures = array_filter(
            $responses,
            fn ($response) => $response->getStatusCode() === 409
        );

        $this->assertCount(1, $successes, 'Exactly one customer should purchase the last item.');
        $this->assertCount(7, $stockFailures, 'All remaining requests should fail as out of stock.');
        $this->assertSame(0, $product->fresh()->stock);
        $this->assertDatabaseCount('orders', 1);

        foreach ($stockFailures as $response) {
            $response->assertJsonPath('success', false);
            $this->assertStringContainsString(
                'Insufficient stock',
                $response->json('message')
            );
        }
    }

    public function test_inventory_grpc_deadline_failure_returns_without_hanging(): void
    {
        $customer = User::factory()->create();
        $product = $this->createProduct(stock: 1);
        $inventory = \Mockery::mock(InventoryGateway::class);
        $inventory->shouldReceive('reserveProducts')
            ->once()
            ->andThrow(new RuntimeException(
                'Inventory gRPC request failed (4): Deadline exceeded'
            ));
        $inventory->shouldReceive('releaseReservation')
            ->once()
            ->andReturn(['success' => true, 'message' => 'Released']);
        $stockManager = new GrpcStockManager($inventory);

        $this->app->instance(StockManager::class, $stockManager);

        $response = $this->actingAs($customer, 'api')
            ->postJson('/api/v1/orders', [
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 1,
                    ],
                ],
            ]);

        $response->assertStatus(503)
            ->assertJsonPath('success', false);
        $this->assertStringContainsString(
            'Inventory gRPC request failed',
            $response->json('message')
        );
        $this->assertStringContainsString(
            'Deadline exceeded',
            $response->json('message')
        );
        $this->assertDatabaseCount('orders', 0);
    }

    private function createOrderFor(User $customer): Order
    {
        return Order::query()->create([
            'user_id' => $customer->id,
            'total_amount' => 25.00,
            'status' => Order::STATUS_PENDING,
        ]);
    }

    private function createProduct(int $stock): Product
    {
        $category = Category::query()->create([
            'name' => fake()->unique()->word(),
        ]);

        return Product::query()->create([
            'name' => 'Defense demo product',
            'description' => 'Test product',
            'price' => 25.00,
            'photo' => 'products/defense-demo.jpg',
            'stock' => $stock,
            'category_id' => $category->id,
        ]);
    }
}
