<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\Auth\AdminLoginController;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Correct-horse-42!';

    private function user(array $attributes = []): User
    {
        return User::factory()->create(['email' => 'staff@example.com', 'password' => self::PASSWORD] + $attributes);
    }

    private function attempt(string $email = 'staff@example.com', string $password = self::PASSWORD, string $ip = '192.0.2.10')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->from(route('admin.login'))
            ->post(route('admin.login.submit'), ['email' => $email, 'password' => $password]);
    }

    public function test_active_user_signs_in_and_it_is_audited(): void
    {
        $user = $this->user();

        $this->attempt()->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'login', 'user_id' => $user->id, 'ip_address' => '192.0.2.10']);
    }

    public function test_session_id_is_regenerated_on_login(): void
    {
        $this->user();
        $this->startSession();
        $before = session()->getId();

        // Control: the session cookie is honoured, so a failed attempt keeps
        // the same id (otherwise the assertion below would prove nothing).
        $this->withCookie(config('session.cookie'), $before)->attempt(password: 'wrong-password');
        $this->assertSame($before, session()->getId());

        $this->withCookie(config('session.cookie'), $before)->attempt()->assertRedirect();

        $this->assertNotSame($before, session()->getId());
    }

    public function test_wrong_password_unknown_email_and_inactive_account_get_the_same_error(): void
    {
        $this->user();
        User::factory()->inactive()->create(['email' => 'former@example.com', 'password' => self::PASSWORD]);

        foreach ([
            ['staff@example.com', 'wrong-password'],
            ['nobody@example.com', self::PASSWORD],
            ['former@example.com', self::PASSWORD],
        ] as [$email, $password]) {
            $this->attempt($email, $password)
                ->assertRedirect(route('admin.login'))
                ->assertSessionHasErrors(['email' => AdminLoginController::FAILED_MESSAGE]);
            $this->assertGuest();
        }

        $this->assertEqualsCanonicalizing(
            ['invalid_password', 'unknown_email', 'inactive'],
            AuditLog::where('action', 'login_failed')->get()->pluck('new_values.reason')->all(),
        );
        $this->assertNull(AuditLog::where('action', 'login_failed')->get()
            ->firstWhere('new_values.reason', 'unknown_email')->user_id);
    }

    public function test_inactive_user_cannot_sign_in_even_with_the_right_password(): void
    {
        User::factory()->inactive()->create(['email' => 'staff@example.com', 'password' => self::PASSWORD]);

        $this->attempt()->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_throttles_after_max_failures_with_retry_after_and_recovers(): void
    {
        $this->user();

        for ($i = 0; $i < AdminLoginController::MAX_ATTEMPTS; $i++) {
            $this->attempt(password: 'wrong-password')->assertSessionHasErrors('email');
        }

        // Even the correct password is refused while throttled.
        $response = $this->attempt();
        $response->assertStatus(429)->assertHeader('Retry-After');
        $this->assertLessThanOrEqual(AdminLoginController::DECAY_SECONDS, (int) $response->headers->get('Retry-After'));
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'login_throttled']);

        $this->travel(AdminLoginController::DECAY_SECONDS + 1)->seconds();

        $this->attempt()->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated();
    }

    public function test_throttle_is_per_email_and_ip(): void
    {
        $this->user();
        User::factory()->create(['email' => 'other@example.com', 'password' => self::PASSWORD]);

        for ($i = 0; $i < AdminLoginController::MAX_ATTEMPTS; $i++) {
            $this->attempt(password: 'wrong-password');
        }

        $this->attempt()->assertStatus(429);
        $this->attempt('other@example.com')->assertRedirect(route('admin.dashboard'));
        $this->post(route('admin.logout'));
        $this->attempt(ip: '192.0.2.99')->assertRedirect(route('admin.dashboard'));
    }

    public function test_successful_login_resets_the_failure_count(): void
    {
        $this->user();

        for ($i = 0; $i < AdminLoginController::MAX_ATTEMPTS - 1; $i++) {
            $this->attempt(password: 'wrong-password');
        }
        $this->attempt()->assertRedirect(route('admin.dashboard'));
        $this->post(route('admin.logout'));

        $this->attempt(password: 'wrong-password')->assertSessionHasErrors('email');
        $this->attempt()->assertRedirect(route('admin.dashboard'));
    }

    public function test_logout_is_post_only_and_audited(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        $this->get('/admin/logout')->assertStatus(405);
        $this->assertAuthenticated();

        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'logout', 'user_id' => $user->id]);
    }

    public function test_user_deactivated_mid_session_is_signed_out(): void
    {
        $user = $this->user();
        $this->actingAs($user);
        $user->forceFill(['status' => 'inactive'])->save();

        $this->get('/admin')->assertRedirect(route('admin.login'));
        $this->assertGuest();
    }

    public function test_signed_in_user_visiting_login_is_sent_to_the_dashboard(): void
    {
        $this->actingAs($this->user())->get(route('admin.login'))->assertRedirect(route('admin.dashboard'));
    }

    public function test_outdated_password_hash_is_upgraded_on_login(): void
    {
        $user = $this->user();
        $user->forceFill(['password' => Hash::make(self::PASSWORD, ['rounds' => 4])])->saveQuietly();
        config(['hashing.bcrypt.rounds' => 5]);
        app('hash')->forgetDrivers();

        $this->attempt()->assertRedirect(route('admin.dashboard'));

        $this->assertFalse(Hash::needsRehash($user->fresh()->password));
    }
}
