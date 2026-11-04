<?php

namespace Modules\User\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Modules\User\Models\User;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class SignOutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['jwt.secret' => '01234567890123456789012345678901']);
    }

    public function test_sign_out_requires_authentication(): void
    {
        $this->postJson('/api/v1/users/sign-out')
            ->assertUnauthorized()
            ->assertJsonPath('success', false);

        $this->postJson('/api/v1/user/sign-out')
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    public function test_sign_out_invalidates_the_authenticated_token(): void
    {
        $token = JWTAuth::fromUser(User::factory()->create());

        $this->withToken($token)
            ->postJson('/api/v1/user/sign-out')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'User signed out successfully');

        Auth::forgetGuards();
        $this->assertFalse(JWTAuth::setToken($token)->check());

        $this->withToken($token)
            ->postJson('/api/v1/users/sign-out')
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }
}
