<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_it_adds_security_headers_to_responses(): void
    {
        $response = $this->get('/up');

        $response
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertHeader(
                'Content-Security-Policy',
                "default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'none'; "
                . "form-action 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; "
                . "img-src 'self' data:; font-src 'self' data:; connect-src 'self'; "
                . "media-src 'self'; worker-src 'self'"
            )
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_it_adds_hsts_only_for_secure_requests(): void
    {
        $response = $this->get('https://localhost/up');

        $response
            ->assertOk()
            ->assertHeader(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
    }
}
