<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The real admin sign-in, forgot/reset password and 429 screens.
 */
class AdminAuthViewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_has_labelled_fields_and_a_reset_link(): void
    {
        $html = $this->get(route('admin.login'))->assertOk()->assertViewIs('admin.auth.login')->getContent();

        $this->assertStringContainsString('<label for="field-email">', $html);
        $this->assertStringContainsString('autocomplete="username"', $html);
        $this->assertStringContainsString('autocomplete="current-password"', $html);
        $this->assertStringContainsString('<label for="field-remember">', $html);
        $this->assertStringContainsString('href="'.route('admin.password.request').'"', $html);
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    public function test_failed_login_shows_the_generic_error_and_keeps_the_email(): void
    {
        $this->followingRedirects()->from(route('admin.login'))
            ->post(route('admin.login.submit'), ['email' => 'nobody@example.com', 'password' => 'wrong-password'])
            ->assertOk()
            ->assertSee(AdminLoginController::FAILED_MESSAGE)
            ->assertSee('value="nobody@example.com"', false)
            ->assertDontSee('No account', false);
    }

    public function test_throttled_login_shows_the_admin_429_page_with_retry_after(): void
    {
        for ($i = 0; $i < AdminLoginController::MAX_ATTEMPTS; $i++) {
            $this->post(route('admin.login.submit'), ['email' => 'a@example.com', 'password' => 'wrong-password']);
        }

        $this->post(route('admin.login.submit'), ['email' => 'a@example.com', 'password' => 'wrong-password'])
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertViewIs('admin.errors.429')
            ->assertSee('Too many attempts')
            ->assertSee('Please try again in');
    }

    public function test_forgot_password_page_and_its_neutral_confirmation(): void
    {
        Notification::fake();
        $this->get(route('admin.password.request'))->assertOk()->assertViewIs('admin.auth.forgot-password')
            ->assertSee('<label for="field-email">', false);

        $this->followingRedirects()->from(route('admin.password.request'))
            ->post(route('admin.password.email'), ['email' => 'nobody@example.com'])
            ->assertOk()
            ->assertSee(PasswordResetLinkController::SENT_MESSAGE);
    }

    public function test_password_reset_throttle_uses_the_admin_429_page(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('admin.password.email'), ['email' => 'nobody@example.com']);
        }

        $this->post(route('admin.password.email'), ['email' => 'nobody@example.com'])
            ->assertStatus(429)->assertViewIs('admin.errors.429');
    }

    public function test_public_rate_limits_still_use_the_public_429_page(): void
    {
        Notification::fake();
        $data = ['name' => 'A', 'email' => 'a@example.com', 'subject' => 'S', 'message' => 'M', 'consent' => '1'];
        for ($i = 0; $i < 3; $i++) {
            $this->post(route('contact.store'), $data);
        }

        $this->post(route('contact.store'), $data)->assertStatus(429)->assertSee('Too many requests');
    }

    public function test_reset_page_prefills_email_and_carries_the_token(): void
    {
        $this->get(route('admin.password.reset', ['token' => 'tok123', 'email' => 'a@example.com']))
            ->assertOk()
            ->assertViewIs('admin.auth.reset-password')
            ->assertSee('<input type="hidden" name="token" value="tok123">', false)
            ->assertSee('value="a@example.com"', false)
            ->assertSee('autocomplete="new-password"', false);
    }

    public function test_change_password_page_uses_the_admin_shell(): void
    {
        $html = $this->actingAs(User::factory()->create())
            ->get(route('admin.account.password.edit'))->assertOk()->getContent();

        $this->assertStringContainsString('<a class="skip-link" href="#main">', $html);
        $this->assertStringContainsString('<nav id="admin-nav" class="admin-nav" aria-label="Admin">', $html);
        $this->assertStringContainsString('<label for="field-current_password">', $html);
        $this->assertStringContainsString('action="'.route('admin.logout').'"', $html);
    }
}
