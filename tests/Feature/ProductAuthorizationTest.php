<?php

namespace Tests\Feature;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Modules\User\Models\User;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ProductAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['jwt.secret' => '01234567890123456789012345678901']);
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_customer_cannot_delete_a_product(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole('user');
        $product = $this->createProduct();

        $this->withToken($this->tokenFor($customer))
            ->deleteJson("/api/v1/products/{$product->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_admin_can_delete_a_product(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $product = $this->createProduct();

        $this->withToken($this->tokenFor($admin))
            ->deleteJson("/api/v1/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    private function createProduct(): Product
    {
        $category = Category::create(['name' => 'Authorization Category ' . uniqid()]);

        return Product::create([
            'name' => 'Authorization Product ' . uniqid(),
            'description' => 'Authorization test product',
            'price' => 10.00,
            'photo' => 'tests/authorization-product.jpg',
            'stock' => 5,
            'category_id' => $category->id,
        ]);
    }

    private function tokenFor(User $user): string
    {
        return JWTAuth::fromUser($user);
    }
}
