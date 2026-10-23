<?php

namespace Tests\Feature\Site;

use App\Models\ContactSubmission;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpiredPageAndMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    /** Real CSRF checking, which Laravel switches off under PHPUnit. */
    private function enforceCsrf(): void
    {
        $this->app->bind(PreventRequestForgery::class, EnforcingForgeryMiddleware::class);
    }

    private function message(): array
    {
        return [
            'name' => 'Ada Example', 'email' => 'ada@visitor.test', 'phone' => '+1 555 0123',
            'organization' => 'Example Ltd', 'subject' => 'Review', 'message' => "A long message\nover two lines.",
            'consent' => '1', 'website' => '',
        ];
    }

    public function test_an_expired_contact_form_returns_to_the_form_with_the_message_kept(): void
    {
        $this->enforceCsrf();

        $response = $this->from(route('contact'))->post(route('contact.store'), $this->message());

        $response->assertRedirect(route('contact'));
        $response->assertSessionHasErrors('session');
        $response->assertSessionHasInput('message', "A long message\nover two lines.");
        $response->assertSessionHasInput('email', 'ada@visitor.test');
        $this->assertSame(0, ContactSubmission::count(), 'Nothing is stored without a valid token.');

        // What the visitor then sees, following the redirect with the same session.
        $page = $this->followingRedirects()->from(route('contact'))->post(route('contact.store'), $this->message())->assertOk();
        $page->assertSee('Your page had been open for a while');
        $page->assertSee('over two lines.', false);
        $page->assertSee('ada@visitor.test', false);
    }

    public function test_consent_and_the_honeypot_are_not_carried_over(): void
    {
        $this->enforceCsrf();

        $response = $this->from(route('contact'))->post(route('contact.store'), $this->message() + ['_token' => 'stale']);

        $response->assertSessionMissing('_old_input.consent');
        $response->assertSessionMissing('_old_input.website');
        $response->assertSessionMissing('_old_input._token');
        $this->followingRedirects()->from(route('contact'))->post(route('contact.store'), $this->message())
            ->assertSee('ada@visitor.test', false)
            ->assertDontSee('checked', false);
    }

    public function test_the_retry_after_an_expired_session_can_then_be_submitted(): void
    {
        $this->enforceCsrf();
        $this->from(route('contact'))->post(route('contact.store'), $this->message());

        $token = csrf_token();
        $this->app->bind(PreventRequestForgery::class, PreventRequestForgery::class);
        $this->from(route('contact'))->post(route('contact.store'), $this->message() + ['_token' => $token])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, ContactSubmission::count());
    }

    public function test_an_admin_form_gets_the_branded_expired_page_and_no_input_echo(): void
    {
        $this->enforceCsrf();

        $this->post(route('admin.login.submit'), ['email' => 'a@b.test', 'password' => 'secret-pass-123'])
            ->assertStatus(419)
            ->assertSee('This page has expired')
            ->assertSee('Go to sign in')
            ->assertSee('noindex, nofollow', false)
            ->assertDontSee('secret-pass-123');
    }

    public function test_the_generic_419_page_is_branded_noindex_and_has_no_inline_styles(): void
    {
        $html = $this->view('errors.419')->__toString();

        $this->assertStringContainsString('This page has expired', $html);
        $this->assertStringContainsString('<meta name="robots" content="noindex">', $html);
        $this->assertStringNotContainsString(' style="', $html);
        $this->assertStringNotContainsString('<style', $html);
    }

    public function test_the_503_page_is_standalone_branded_noindex_and_needs_no_database(): void
    {
        $queries = 0;
        \DB::listen(function () use (&$queries) {
            $queries++;
        });

        $html = $this->view('errors.503')->__toString();

        $this->assertSame(0, $queries);
        $this->assertStringContainsString("We'll be back shortly", $html);
        $this->assertStringContainsString('<meta name="robots" content="noindex">', $html);
        $this->assertStringContainsString('css/site.css?v=', $html);
        $this->assertStringNotContainsString(' style="', $html);
        $this->assertStringNotContainsString('<style', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_a_site_in_maintenance_mode_shows_the_503_page_with_the_security_headers(): void
    {
        $this->artisan('down')->assertSuccessful();
        try {
            $this->get('/')
                ->assertStatus(503)
                ->assertSee("We'll be back shortly", false)
                ->assertHeader('X-Frame-Options', 'DENY')
                ->assertHeader('Content-Security-Policy');
        } finally {
            $this->artisan('up');
        }
    }
}

class EnforcingForgeryMiddleware extends PreventRequestForgery
{
    protected function runningUnitTests()
    {
        return false;
    }
}
