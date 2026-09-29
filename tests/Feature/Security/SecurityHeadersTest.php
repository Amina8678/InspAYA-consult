<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Security headers (item 2) and forced HTTPS (item 4), on every response.
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    private function assertSecurityHeaders(TestResponse $response): string
    {
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Content-Security-Policy');

        return $response->headers->get('Content-Security-Policy');
    }

    public function test_security_headers_are_present_on_a_public_response(): void
    {
        $csp = $this->assertSecurityHeaders($this->get('/'));

        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
    }

    public function test_security_headers_are_present_on_an_admin_response(): void
    {
        $this->assertSecurityHeaders($this->get(route('admin.login')));

        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->withRole('administrator')->create();
        $this->assertSecurityHeaders($this->actingAs($admin)->get(route('admin.dashboard')));
    }

    public function test_security_headers_are_present_on_an_error_response(): void
    {
        $this->assertSecurityHeaders($this->get('/this-page-does-not-exist'));
    }

    public function test_csp_permits_no_inline_script_and_no_remote_host(): void
    {
        $csp = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("script-src 'self' 'nonce-", $csp);
        $this->assertStringNotContainsString('unsafe-inline', $csp);
        $this->assertStringNotContainsString('unsafe-eval', $csp);
        $this->assertStringNotContainsString('http://', $csp);
        $this->assertStringNotContainsString('https://', $csp);
    }

    public function test_the_nonce_in_the_csp_header_matches_the_rendered_json_ld_scripts(): void
    {
        $response = $this->get('/')->assertOk();
        $csp = $response->headers->get('Content-Security-Policy');

        preg_match("/'nonce-([^']+)'/", $csp, $m);
        $this->assertNotEmpty($m, 'CSP header must carry a nonce');

        $this->assertStringContainsString('nonce="'.$m[1].'"', $response->getContent());
    }

    public function test_each_request_gets_its_own_nonce(): void
    {
        $first = $this->get('/')->headers->get('Content-Security-Policy');
        $second = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertNotSame($first, $second);
    }

    // HTTPS redirect ---------------------------------------------------------

    public function test_http_is_not_redirected_outside_production(): void
    {
        $this->assertNotSame('production', app()->environment());

        $this->get('/')->assertOk();
    }

    public function test_http_is_redirected_to_https_in_production(): void
    {
        app()->detectEnvironment(fn () => 'production');

        // SecurityHeaders wraps ForceHttps, so the redirect itself still
        // carries the headers, not just the page it points to.
        $response = $this->get('http://localhost/services')->assertRedirect('https://localhost/services');
        $this->assertSecurityHeaders($response);

        app()->detectEnvironment(fn () => 'testing');
    }

    public function test_an_already_secure_request_is_not_redirected_even_in_production(): void
    {
        app()->detectEnvironment(fn () => 'production');

        $this->get('https://localhost/services', ['X-Forwarded-Proto' => 'https'])->assertOk();

        app()->detectEnvironment(fn () => 'testing');
    }

    // Trusted proxies -----------------------------------------------------

    protected function tearDown(): void
    {
        // TrustProxies::at() sets process-wide static state; never let one
        // test's trusted-proxy configuration leak into the next.
        TrustProxies::flushState();

        parent::tearDown();
    }

    public function test_an_untrusted_proxys_forwarded_proto_header_is_ignored(): void
    {
        app()->detectEnvironment(fn () => 'production');

        // Plain http connection (no `https://` in the URL, unlike the test
        // above) claiming X-Forwarded-Proto: https. No proxy is configured
        // as trusted, so this must be ignored and treated as insecure.
        $this->get('http://localhost/services', ['X-Forwarded-Proto' => 'https'])
            ->assertRedirect('https://localhost/services');

        app()->detectEnvironment(fn () => 'testing');
    }

    public function test_a_trusted_proxys_forwarded_proto_header_marks_the_request_secure(): void
    {
        // 'REMOTE_ADDR' is a Laravel/Symfony shorthand meaning "trust
        // whichever address actually made this connection" — the test
        // client's requests come from 127.0.0.1, matching what TRUSTED_PROXIES
        // would hold for a proxy reachable only from that address.
        TrustProxies::at('REMOTE_ADDR');
        app()->detectEnvironment(fn () => 'production');

        // Same plain-http request as the test above, but now from a trusted
        // proxy: the forwarded scheme must be honoured, so no redirect loop.
        $this->get('http://localhost/services', ['X-Forwarded-Proto' => 'https'])
            ->assertOk();

        app()->detectEnvironment(fn () => 'testing');
    }
}
