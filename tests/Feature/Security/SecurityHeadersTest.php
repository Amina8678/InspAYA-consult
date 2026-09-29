<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
