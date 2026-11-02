<?php

namespace Tests\Feature;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\User\Models\User;
use Tests\TestCase;

class UserRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['jwt.secret' => '01234567890123456789012345678901']);
    }

    public function test_registration_uses_the_user_service(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $this->postJson('/api/v1/user/sign-up', [
            'name' => 'Module User',
            'email' => 'module@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'module@example.com')
            ->assertJsonPath('data.user.role', 'user')
            ->assertJsonMissingPath('data.user.password')
            ->assertJsonStructure(['data' => ['user' => ['id', 'name', 'email', 'role'], 'token']]);

        $this->assertDatabaseHas('users', ['email' => 'module@example.com']);
    }

    public function test_sign_in_request_is_loaded_from_the_user_module(): void
    {
        $this->postJson('/api/v1/user/sign-in', [])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['data' => ['email', 'password']]);
    }

    public function test_sign_in_returns_user_information_and_role(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->postJson('/api/v1/user/sign-in', [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.role', 'admin')
            ->assertJsonMissingPath('data.user.password');
    }

    public function test_database_seeder_creates_customers_and_one_admin(): void
    {
        $this->seed();

        $this->assertDatabaseCount('users', 4);
        $this->assertTrue(User::where('email', 'admin@example.test')->firstOrFail()->hasRole('admin'));
        $this->assertSame(
            3,
            User::role('user')->count(),
        );
    }
}
