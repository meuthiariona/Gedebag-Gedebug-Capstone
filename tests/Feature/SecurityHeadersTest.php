<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Bukti perbaikan temuan OWASP ZAP: header keamanan & validasi input. */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_security_headers_are_sent_on_every_page(): void
    {
        foreach (['/', '/login', '/packages'] as $url) {
            $res = $this->get($url);
            $res->assertHeader('X-Frame-Options', 'SAMEORIGIN');
            $res->assertHeader('X-Content-Type-Options', 'nosniff');
            $res->assertHeader('Referrer-Policy');
            $this->assertStringContainsString("frame-ancestors 'self'", $res->headers->get('Content-Security-Policy'));
            $this->assertStringContainsString("object-src 'none'", $res->headers->get('Content-Security-Policy'));
            $res->assertHeaderMissing('X-Powered-By');
        }
    }

    public function test_my_bookings_rejects_malformed_filters(): void
    {
        $this->actingAs(User::where('role', 'customer')->first());

        $this->get('/my-bookings?month=2026-11&q=konser')->assertOk();
        $this->get('/my-bookings?month='.urlencode(' AND 1=1 -- '))->assertSessionHasErrors('month');
        $this->get('/my-bookings?tab=hacked')->assertSessionHasErrors('tab');
        $this->get('/my-bookings?q='.str_repeat('a', 101))->assertSessionHasErrors('q');
    }
}
