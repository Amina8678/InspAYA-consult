<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\Admin\AccountPasswordController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Models\User;
use App\Notifications\AdminResetPassword;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PasswordTest extends TestCase
{
    use RefreshDatabase;

    private const OLD = 'Old-password-123!';

    private const NEW = 'New-password-456?';

    /**
     * @return array<string, array{string}>
     */
    public static function weakPasswords(): array
    {
        return [
            'too short' => ['Aa1!short'],
            'no symbol' => ['Abcdefgh12345'],
            'no number' => ['Abcdefgh!!!!!'],
            'no uppercase' => ['abcdefgh123!!'],
        ];
    }

    // Change own password -------------------------------------------------

    public function test_user_changes_own_password_with_current_password(): void
    {
        $user = User::factory()->create(['password' => self::OLD]);

        $this->actingAs($user)
            ->put(route('admin.account.password.update'), [
                'current_password' => self::OLD,
                'password' => self::NEW,
                'password_confirmation' => self::NEW,
            ])
            ->assertRedirect(route('admin.account.password.edit'))
            ->assertSessionHas('status', AccountPasswordController::UPDATED_MESSAGE);

        $this->get(route('admin.account.password.edit'))->assertOk()
            ->assertSee(AccountPasswordController::UPDATED_MESSAGE);

        $this->assertTrue(Hash::check(self::NEW, $user->fresh()->password));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('audit_logs', ['action' => 'password_changed', 'user_id' => $user->id]);
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => self::OLD]);

        $this->actingAs($user)->put(route('admin.account.password.update'), [
            'current_password' => 'not-my-password',
            'password' => self::NEW,
            'password_confirmation' => self::NEW,
        ])->assertSessionHasErrorsIn('updatePassword', 'current_password');

        $this->assertTrue(Hash::check(self::OLD, $user->fresh()->password));
    }

    #[DataProvider('weakPasswords')]
    public function test_new_password_must_meet_the_policy(string $weak): void
    {
        $user = User::factory()->create(['password' => self::OLD]);

        $this->actingAs($user)->put(route('admin.account.password.update'), [
            'current_password' => self::OLD,
            'password' => $weak,
            'password_confirmation' => $weak,
        ])->assertSessionHasErrorsIn('updatePassword', 'password');

        $this->assertTrue(Hash::check(self::OLD, $user->fresh()->password));
    }

    public function test_guest_cannot_change_a_password(): void
    {
        $this->put(route('admin.account.password.update'))->assertRedirect(route('admin.login'));
    }

    // Reset link ----------------------------------------------------------

    public function test_reset_link_is_queued_for_an_active_user(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->from(route('admin.password.request'))
            ->post(route('admin.password.email'), ['email' => $user->email])
            ->assertRedirect(route('admin.password.request'))
            ->assertSessionHas('status', PasswordResetLinkController::SENT_MESSAGE);

        Notification::assertSentTo($user, AdminResetPassword::class, function (AdminResetPassword $notification) use ($user) {
            $url = $notification->toMail($user)->actionUrl;

            $this->assertInstanceOf(ShouldQueue::class, $notification);
            $this->assertStringContainsString('/admin/reset-password/', $url);

            return true;
        });
        $this->assertDatabaseHas('audit_logs', ['action' => 'password_reset_requested', 'user_id' => $user->id]);
    }

    public function test_reset_email_goes_through_the_queue(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->post(route('admin.password.email'), ['email' => $user->email]);

        Queue::assertPushed(SendQueuedNotifications::class);
    }

    public function test_unknown_and_inactive_emails_get_the_same_answer_and_no_email(): void
    {
        Notification::fake();
        $inactive = User::factory()->inactive()->create();

        foreach (['nobody@example.com', $inactive->email] as $email) {
            $this->post(route('admin.password.email'), ['email' => $email])
                ->assertSessionHas('status', PasswordResetLinkController::SENT_MESSAGE)
                ->assertSessionHasNoErrors();
        }

        Notification::assertNothingSent();
    }

    public function test_reset_link_requests_are_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('admin.password.email'), ['email' => 'nobody@example.com'])->assertRedirect();
        }

        $this->post(route('admin.password.email'), ['email' => 'nobody@example.com'])->assertStatus(429);
    }

    // Reset password ------------------------------------------------------

    public function test_reset_page_receives_token_and_email(): void
    {
        $this->get(route('admin.password.reset', ['token' => 'abc', 'email' => 'a@example.com']))
            ->assertOk()
            ->assertViewIs('admin.auth.reset-password')
            ->assertViewHas('token', 'abc')
            ->assertViewHas('email', 'a@example.com');
    }

    public function test_valid_token_resets_the_password_once(): void
    {
        $user = User::factory()->create(['password' => self::OLD]);
        $token = Password::broker()->createToken($user);

        $payload = ['token' => $token, 'email' => $user->email, 'password' => self::NEW, 'password_confirmation' => self::NEW];

        $this->post(route('admin.password.store'), $payload)
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('status', NewPasswordController::RESET_MESSAGE);

        $this->assertTrue(Hash::check(self::NEW, $user->fresh()->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'password_reset', 'user_id' => $user->id]);

        // The token is single-use.
        $this->post(route('admin.password.store'), $payload)
            ->assertSessionHasErrors(['email' => NewPasswordController::INVALID_MESSAGE]);
    }

    public function test_reset_enforces_the_password_policy(): void
    {
        $user = User::factory()->create(['password' => self::OLD]);
        $token = Password::broker()->createToken($user);

        $this->post(route('admin.password.store'), [
            'token' => $token, 'email' => $user->email, 'password' => 'weak', 'password_confirmation' => 'weak',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check(self::OLD, $user->fresh()->password));
    }

    public function test_bad_token_unknown_email_and_inactive_user_get_the_same_error(): void
    {
        $active = User::factory()->create(['password' => self::OLD]);
        $inactive = User::factory()->inactive()->create(['password' => self::OLD]);
        $inactiveToken = Password::broker()->createToken($inactive);

        foreach ([
            ['bad-token', $active->email],
            ['bad-token', 'nobody@example.com'],
            [$inactiveToken, $inactive->email],
        ] as [$token, $email]) {
            $this->post(route('admin.password.store'), [
                'token' => $token, 'email' => $email, 'password' => self::NEW, 'password_confirmation' => self::NEW,
            ])->assertSessionHasErrors(['email' => NewPasswordController::INVALID_MESSAGE]);
        }

        $this->assertTrue(Hash::check(self::OLD, $inactive->fresh()->password));
    }
}
