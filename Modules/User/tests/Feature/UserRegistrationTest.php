<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->postJson('/api/signup', [
            'name' => 'Module User',
            'email' => 'module@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'module@example.com')
            ->assertJsonStructure(['data' => ['user' => ['id', 'name', 'email'], 'token']]);

        $this->assertDatabaseHas('users', ['email' => 'module@example.com']);
    }

    public function test_sign_in_request_is_loaded_from_the_user_module(): void
    {
        $this->postJson('/api/signin', [])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['data' => ['email', 'password']]);
    }
}
