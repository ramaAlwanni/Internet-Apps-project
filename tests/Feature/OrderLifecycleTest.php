<?php

namespace Tests\Feature;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Order\Models\Order;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Modules\User\Models\User;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class OrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['jwt.secret' => '01234567890123456789012345678901']);
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_user_can_add_product_to_cart_and_checkout(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $product = $this->createProduct(10.00, 5);
        $token = JWTAuth::fromUser($user);

        $this->withToken($token)
            ->postJson('/api/v1/orders/cart/items', [
                'product_id' => $product->id,
                'quantity' => 2,
            ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $this->withToken($token)
            ->postJson('/api/v1/orders/cart/checkout')
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', Order::STATUS_PENDING)
            ->assertJsonPath('data.items.0.product_id', $product->id)
            ->assertJsonPath('data.items.0.product_name', $product->name)
            ->assertJsonPath('data.items.0.quantity', 2);

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'status' => Order::STATUS_PENDING,
        ]);

        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 2,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 3,
        ]);
    }

    public function test_checkout_fails_when_cart_is_empty(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $token = JWTAuth::fromUser($user);

        $this->withToken($token)
            ->postJson('/api/v1/orders/cart/checkout')
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath(
                'message',
                'Cannot create an order from an empty cart.'
            );
    }

    public function test_product_not_found_is_rejected_before_cart_insert(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $token = JWTAuth::fromUser($user);

        $this->withToken($token)
            ->postJson('/api/v1/orders/cart/items', [
                'product_id' => 999999,
                'quantity' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'data.product_id.0',
                'The selected product is invalid.'
            );
    }

    public function test_order_keeps_product_name_and_price_snapshot(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $product = $this->createProduct(25.00, 5);
        $token = JWTAuth::fromUser($user);

        $this->withToken($token)
            ->postJson('/api/v1/orders/cart/items', [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertCreated();

        $this->withToken($token)
            ->postJson('/api/v1/orders/cart/checkout')
            ->assertCreated();

        $product->update([
            'name' => 'Changed Product Name',
            'price' => 99.00,
        ]);

        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'product_name' => 'Test Product',
            'unit_price' => '25.00',
        ]);
    }

    public function test_users_have_separate_carts(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $firstUser->assignRole('user');
        $secondUser->assignRole('user');
        $product = $this->createProduct(10.00, 10);

        $this->actingAs($firstUser, 'api')
            ->postJson('/api/v1/orders/cart/items', [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertCreated();

        $this->app['auth']->forgetGuards();

        $this->actingAs($secondUser, 'api')
            ->getJson('/api/v1/orders/cart')
            ->assertOk()
            ->assertJsonPath('data.user_id', $secondUser->id)
            ->assertJsonCount(0, 'data.items');
    }

    private function createProduct(float $price, int $stock): Product
    {
        $category = Category::create(['name' => 'Test Category ' . uniqid()]);

        return Product::create([
            'name' => 'Test Product',
            'description' => 'Test product',
            'price' => $price,
            'photo' => 'tests/test-product.jpg',
            'stock' => $stock,
            'category_id' => $category->id,
        ]);
    }
}
