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

class OrderAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['jwt.secret' => '01234567890123456789012345678901']);
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_user_sees_only_their_own_orders(): void
    {
        $customerOne = User::factory()->create();
        $customerTwo = User::factory()->create();
        $customerOne->assignRole('user');
        $customerTwo->assignRole('user');

        $orderOne = $this->createOrderFor($customerOne);
        $orderTwo = $this->createOrderFor($customerTwo);

        $response = $this->withToken($this->tokenFor($customerOne))
            ->getJson('/api/v1/orders');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $orderOne->id);

        $response->assertJsonMissing(['id' => $orderTwo->id]);
    }

    public function test_user_orders_are_paginated_and_page_size_is_limited(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $category = Category::create(['name' => 'Pagination Category ' . uniqid()]);
        $product = Product::create([
            'name' => 'Pagination Product ' . uniqid(),
            'description' => 'Pagination test product',
            'price' => 10.00,
            'photo' => 'tests/pagination-product.jpg',
            'stock' => 20,
            'category_id' => $category->id,
        ]);

        for ($index = 0; $index < 17; $index++) {
            $order = Order::create([
                'user_id' => $user->id,
                'total_amount' => 10.00,
                'status' => Order::STATUS_PENDING,
            ]);
            $order->items()->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'unit_price' => $product->price,
                'quantity' => 1,
                'subtotal' => 10.00,
            ]);
        }

        $token = $this->tokenFor($user);
        $firstPage = $this->withToken($token)
            ->getJson('/api/v1/orders?per_page=5&page=1')
            ->assertOk()
            ->assertJsonPath('data.current_page', 1)
            ->assertJsonPath('data.per_page', 5)
            ->assertJsonPath('data.last_page', 4)
            ->assertJsonCount(5, 'data.data');

        $secondPage = $this->withToken($token)
            ->getJson('/api/v1/orders?per_page=5&page=2')
            ->assertOk()
            ->assertJsonPath('data.current_page', 2)
            ->assertJsonCount(5, 'data.data');

        $this->assertNotSame(
            $firstPage->json('data.data.0.id'),
            $secondPage->json('data.data.0.id')
        );

        $this->withToken($token)
            ->getJson('/api/v1/orders?per_page=100')
            ->assertUnprocessable()
            ->assertJsonStructure(['data' => ['per_page']]);
    }

    public function test_user_cannot_view_another_users_order_details(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $owner->assignRole('user');
        $otherUser->assignRole('user');

        $order = $this->createOrderFor($owner);

        $this->withToken($this->tokenFor($otherUser))
            ->getJson("/api/v1/orders/{$order->id}")
            ->assertNotFound();
    }

    public function test_user_cannot_access_admin_order_list(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/orders/admin')
            ->assertForbidden();
    }

    public function test_admin_can_view_all_orders_and_any_order_details(): void
    {
        $admin = User::factory()->create();
        $customerOne = User::factory()->create();
        $customerTwo = User::factory()->create();
        $admin->assignRole('admin');
        $customerOne->assignRole('user');
        $customerTwo->assignRole('user');

        $orderOne = $this->createOrderFor($customerOne);
        $orderTwo = $this->createOrderFor($customerTwo);
        $adminToken = $this->tokenFor($admin);

        $this->withToken($adminToken)
            ->getJson('/api/v1/orders/admin')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.data');

        $this->withToken($adminToken)
            ->getJson("/api/v1/orders/admin/{$orderOne->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $orderOne->id);

        $this->assertNotSame($orderOne->user_id, $orderTwo->user_id);
    }

    public function test_user_cannot_change_order_status(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $order = $this->createOrderFor($user);

        $this->withToken($this->tokenFor($user))
            ->putJson("/api/v1/orders/{$order->id}/status", [
                'status' => Order::STATUS_CONFIRMED,
            ])
            ->assertForbidden();
    }

    public function test_admin_can_change_order_status_through_allowed_transition(): void
    {
        $admin = User::factory()->create();
        $customer = User::factory()->create();
        $admin->assignRole('admin');
        $customer->assignRole('user');
        $order = $this->createOrderFor($customer);

        $this->withToken($this->tokenFor($admin))
            ->putJson("/api/v1/orders/{$order->id}/status", [
                'status' => Order::STATUS_CONFIRMED,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', Order::STATUS_CONFIRMED);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => Order::STATUS_CONFIRMED,
        ]);
    }

    private function createOrderFor(User $user): Order
    {
        $category = Category::create(['name' => 'Test Category ' . uniqid()]);
        $product = Product::create([
            'name' => 'Test Product ' . uniqid(),
            'description' => 'Test product',
            'price' => 10.00,
            'photo' => 'tests/test-product.jpg',
            'stock' => 20,
            'category_id' => $category->id,
        ]);

        $order = Order::create([
            'user_id' => $user->id,
            'total_amount' => 10.00,
            'status' => Order::STATUS_PENDING,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => $product->price,
            'quantity' => 1,
            'subtotal' => 10.00,
        ]);

        return $order;
    }

    private function tokenFor(User $user): string
    {
        return JWTAuth::fromUser($user);
    }
}
