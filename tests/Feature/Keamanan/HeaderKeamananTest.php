<?php

namespace Tests\Feature\Keamanan;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

class HeaderKeamananTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_security_headers_are_sent_on_pages_and_api(): void
    {
        foreach (['/', '/api/dinas'] as $url) {
            $response = $this->get($url);

            $response->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('X-Frame-Options', 'DENY')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
                ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
                ->assertHeaderMissing('X-Powered-By');

            $this->assertStringContainsString('camera=()', $response->headers->get('Permissions-Policy'));
        }
    }

    public function test_hsts_is_only_sent_over_https(): void
    {
        $this->get('/api/dinas')->assertHeaderMissing('Strict-Transport-Security');

        $this->get('https://localhost/api/dinas')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_enforced_csp_blocks_inline_scripts_but_allows_nonced_ones(): void
    {
        config(['keamanan.csp' => 'enforce']);

        $csp = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertNotNull($csp);
        $this->assertStringContainsString("script-src 'self' 'nonce-".Vite::cspNonce()."'", $csp);
        $this->assertStringNotContainsString("'unsafe-inline'", str($csp)->betweenFirst('script-src', ';')->toString());
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
    }

    public function test_report_only_mode_uses_report_only_header(): void
    {
        config(['keamanan.csp' => 'report']);

        $this->get('/')
            ->assertHeaderMissing('Content-Security-Policy')
            ->assertHeader('Content-Security-Policy-Report-Only');
    }

    public function test_csp_is_off_by_default_only_in_local_development(): void
    {
        $this->assertSame('off', config('keamanan.csp'));

        $this->get('/')
            ->assertHeaderMissing('Content-Security-Policy')
            ->assertHeaderMissing('Content-Security-Policy-Report-Only');
    }

    public function test_staging_enforces_csp_by_default(): void
    {
        config(['app.env' => 'staging', 'keamanan.csp' => 'enforce']);

        $this->get('/')
            ->assertHeader('Content-Security-Policy')
            ->assertHeaderMissing('Content-Security-Policy-Report-Only');
    }

    public function test_cors_only_allows_the_app_origin(): void
    {
        $asal = rtrim(config('app.url'), '/');
        $preflight = fn (string $origin) => $this->call('OPTIONS', '/api/dinas', server: [
            'HTTP_ORIGIN' => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);

        $this->assertSame($asal, $preflight($asal)->headers->get('Access-Control-Allow-Origin'));

        // Browser hanya mengizinkan pembacaan lintas-origin bila header ini sama
        // persis dengan origin peminta; origin asing tidak pernah dipantulkan.
        $this->assertNotSame('https://evil.example', $preflight('https://evil.example')->headers->get('Access-Control-Allow-Origin'));
        $this->assertNotSame('*', $preflight('https://evil.example')->headers->get('Access-Control-Allow-Origin'));
    }
}
