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

    public function test_one_ip_cycling_through_emails_is_throttled_with_bounded_audit_rows(): void
    {
        $statuses = [];
        for ($i = 1; $i <= 30; $i++) {
            $statuses[] = $this->attempt("spray{$i}@example.com", 'wrong-password', '192.0.2.50')->getStatusCode();
        }

        $this->assertSame(
            array_merge(array_fill(0, AdminLoginController::IP_MAX_ATTEMPTS, 302), array_fill(0, 30 - AdminLoginController::IP_MAX_ATTEMPTS, 429)),
            $statuses,
        );
        $this->assertSame(AdminLoginController::IP_MAX_ATTEMPTS, AuditLog::where('action', 'login_failed')->count());
        $this->assertSame(1, AuditLog::where('action', 'login_throttled')->count());
    }

    public function test_requests_past_the_ip_limit_write_no_more_rows_however_many_arrive(): void
    {
        for ($i = 1; $i <= AdminLoginController::IP_MAX_ATTEMPTS; $i++) {
            $this->attempt("spray{$i}@example.com", 'wrong-password', '192.0.2.51');
        }
        $rows = AuditLog::count();

        for ($i = 1; $i <= 50; $i++) {
            $this->attempt("more{$i}@example.com", 'wrong-password', '192.0.2.51')->assertStatus(429)->assertHeader('Retry-After');
        }

        $this->assertSame($rows + 1, AuditLog::count(), 'One login_throttled row for the whole window.');
    }

    public function test_the_ip_limit_blocks_only_that_ip_and_expires(): void
    {
        $this->user();
        for ($i = 1; $i <= AdminLoginController::IP_MAX_ATTEMPTS; $i++) {
            $this->attempt("spray{$i}@example.com", 'wrong-password', '192.0.2.52');
        }

        // Even the right password is refused from the blocked IP; another IP is unaffected.
        $this->attempt(ip: '192.0.2.52')->assertStatus(429);
        $this->attempt(ip: '192.0.2.53')->assertRedirect(route('admin.dashboard'));
        $this->post(route('admin.logout'));

        $this->travel(AdminLoginController::IP_DECAY_SECONDS + 1)->seconds();

        $this->attempt(ip: '192.0.2.52')->assertRedirect(route('admin.dashboard'));
    }

    public function test_a_normal_office_stays_under_the_ip_limit(): void
    {
        $this->user();

        // Five staff behind one NAT each mistype a password twice, then get in.
        for ($i = 1; $i <= 5; $i++) {
            User::factory()->create(['email' => "staff{$i}@example.com", 'password' => self::PASSWORD]);
            $this->attempt("staff{$i}@example.com", 'wrong-password', '192.0.2.54');
            $this->attempt("staff{$i}@example.com", 'wrong-password', '192.0.2.54');
            $this->attempt("staff{$i}@example.com", self::PASSWORD, '192.0.2.54')->assertRedirect(route('admin.dashboard'));
            $this->post(route('admin.logout'));
        }
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

    public function test_repeated_failures_write_at_most_one_login_failed_row_per_email_and_ip_per_minute(): void
    {
        $this->user();

        for ($i = 0; $i < 3; $i++) {
            $this->attempt(password: 'wrong-password')->assertSessionHasErrors('email');
        }
        $this->assertSame(1, AuditLog::where('action', 'login_failed')->count());

        // A different email is a different key.
        $this->attempt('someone-else@example.com', 'wrong-password');
        $this->assertSame(2, AuditLog::where('action', 'login_failed')->count());

        // The window passes: the same email + IP is recorded again.
        $this->travel(AdminLoginController::AUDIT_FAILED_WINDOW_SECONDS + 1)->seconds();
        $this->attempt(password: 'wrong-password');
        $this->assertSame(3, AuditLog::where('action', 'login_failed')->count());
    }

    public function test_requests_while_throttled_write_one_login_throttled_row_per_window(): void
    {
        $this->user();
        for ($i = 0; $i < AdminLoginController::MAX_ATTEMPTS; $i++) {
            $this->attempt(password: 'wrong-password');
        }

        for ($i = 0; $i < 5; $i++) {
            $this->attempt(password: 'wrong-password')->assertStatus(429);
        }
        $this->assertSame(1, AuditLog::where('action', 'login_throttled')->count());

        // Next window: throttled again, one more row.
        $this->travel(AdminLoginController::DECAY_SECONDS + 1)->seconds();
        for ($i = 0; $i < AdminLoginController::MAX_ATTEMPTS; $i++) {
            $this->attempt(password: 'wrong-password');
        }
        $this->attempt(password: 'wrong-password')->assertStatus(429);
        $this->attempt(password: 'wrong-password')->assertStatus(429);
        $this->assertSame(2, AuditLog::where('action', 'login_throttled')->count());
    }
}
