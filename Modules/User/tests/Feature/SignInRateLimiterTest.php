<?php

namespace Modules\User\Tests\Feature;

use Tests\TestCase;
use Modules\User\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Validation\ValidationException;

class SignInRateLimiterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('login');
    }

    #[Test]
    public function it_blocks_sign_in_after_too_many_failed_attempts()
    {
        User::factory()->create([
            'email' => 'yasmine@test.com',
            'password' => bcrypt('correct-password'),
        ]);

        foreach (range(1, 5) as $i) {
            try {
                $this->post(route('sign-in'), [
                    'email' => 'yasmine@test.com',
                    'password' => 'wrong-password',
                ]);
            } catch (ValidationException $e) {
                //Ignore the validation exception to continue the loop
            }
        }

        $response = $this->post(route('sign-in'), [
            'email' => 'yasmine@test.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(429);
    }
}
