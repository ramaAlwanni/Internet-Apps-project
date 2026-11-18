<?php

namespace Tests\Feature;

use App\Contracts\Catalog\ProductCatalog;
use App\Contracts\Identity\UserDirectory;
use App\Contracts\Inventory\InventoryGateway;
use App\Exceptions\InventoryUnavailableException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Order\Contracts\StockManager;
use Modules\Order\Services\OrderService;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Modules\Product\Services\ProductCatalogService;
use Modules\User\Models\User;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class InventoryResilienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_catalog_maps_an_inventory_failure_to_a_clear_exception(): void
    {
        config(['order.stock_manager' => 'grpc']);

        $first = $this->createProduct(5);
        $second = $this->createProduct(5);

        $inventory = \Mockery::mock(InventoryGateway::class);
        $inventory->shouldReceive('ensureProductStock')
            ->once()
            ->andThrow(new InventoryUnavailableException(
                'Inventory service is currently unavailable. Please try again later.'
            ));
        $inventory->shouldNotReceive('getAvailableQuantity');

        try {
            (new ProductCatalogService($inventory))->findByIds([
                $first->id,
                $second->id,
            ]);
            $this->fail('The inventory failure should stop the catalog read.');
        } catch (InventoryUnavailableException $exception) {
            $this->assertSame(
                'Inventory service is currently unavailable. Please try again later.',
                $exception->getMessage()
            );
            $this->assertSame(503, $exception->getHttpCode());
        }
    }

    public function test_product_listing_returns_503_instead_of_500_when_inventory_is_down(): void
    {
        $customer = User::factory()->create();
        $this->createProduct(5);

        $catalog = \Mockery::mock(ProductCatalog::class);
        $catalog->shouldReceive('findByIds')
            ->once()
            ->andThrow(new InventoryUnavailableException());

        $this->app->instance(ProductCatalog::class, $catalog);

        $this->actingAs($customer, 'api')
            ->getJson('/api/v1/products?page=1&per_page=4')
            ->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath(
                'message',
                InventoryUnavailableException::DEFAULT_MESSAGE
            );
    }

    public function test_order_is_rejected_with_a_clear_message_when_stock_is_insufficient(): void
    {
        $customer = User::factory()->create();
        $product = $this->createProduct(1);

        $this->actingAs($customer, 'api')
            ->postJson('/api/v1/orders', [
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 2,
                    ],
                ],
            ])
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath(
                'message',
                'Insufficient stock for product: '
                    . $product->name
                    . ' Please reduce the quantity or choose another product.'
            );

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(1, $product->fresh()->stock);
    }

    public function test_reservation_is_released_when_the_order_cannot_be_persisted(): void
    {
        $stockManager = new class implements StockManager {
            public bool $released = false;

            public function reserve(string $reservationId, array $items): array
            {
                return [
                    'success' => true,
                    'message' => 'Reserved',
                    'reservation_id' => $reservationId,
                ];
            }

            public function commit(string $reservationId): array
            {
                return [
                    'success' => true,
                    'message' => 'Committed',
                    'reservation_id' => $reservationId,
                ];
            }

            public function release(string $reservationId): array
            {
                $this->released = true;

                return [
                    'success' => true,
                    'message' => 'Released',
                    'reservation_id' => $reservationId,
                ];
            }
        };

        $catalog = \Mockery::mock(ProductCatalog::class);
        $catalog->shouldReceive('findSnapshotsByIds')
            ->once()
            ->andReturn([]);

        $service = new OrderService(
            $stockManager,
            $catalog,
            \Mockery::mock(UserDirectory::class)
        );

        try {
            $service->createOrder(1, [
                ['product_id' => 1, 'quantity' => 1],
            ]);
            $this->fail('The order should not be created when its products are missing.');
        } catch (Throwable $exception) {
            $this->assertStringContainsString(
                'One or more products were not found.',
                $exception->getMessage()
            );
        }

        $this->assertTrue($stockManager->released);
        $this->assertDatabaseCount('orders', 0);
    }

    private function createProduct(int $stock): Product
    {
        $category = Category::query()->create([
            'name' => fake()->unique()->word(),
        ]);

        return Product::query()->create([
            'name' => 'Resilience test product',
            'description' => 'Test product',
            'price' => 25.00,
            'photo' => 'products/resilience-test.jpg',
            'stock' => $stock,
            'category_id' => $category->id,
        ]);
    }
}
