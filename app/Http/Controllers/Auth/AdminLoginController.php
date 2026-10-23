<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Admin sign-in and sign-out (FR-ADM-02, NFR-SEC-02/03).
 *
 * - Throttled per email + IP: after MAX_ATTEMPTS failures, 429 with
 *   Retry-After until DECAY_SECONDS pass.
 * - Also throttled per IP across all emails: after IP_MAX_ATTEMPTS failures
 *   in IP_DECAY_SECONDS, every sign-in from that IP gets a 429, so cycling
 *   through emails (password spraying) cannot dodge the per-email limit.
 * - One generic error for unknown email, wrong password and inactive
 *   account, so the response never reveals whether an email exists.
 * - Every attempt is written to the audit log.
 */
class AdminLoginController extends Controller
{
    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 60;

    /** Failed attempts from one IP, whatever the email, before it is blocked (an office behind one NAT fits). */
    public const IP_MAX_ATTEMPTS = 20;

    public const IP_DECAY_SECONDS = 600;

    public const AUDIT_FAILED_WINDOW_SECONDS = 60;

    public const FAILED_MESSAGE = 'These credentials do not match our records.';

    public function __construct(private AuditLogger $audit) {}

    public function showLoginForm(): View
    {
        return view('admin.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $email = Str::lower($credentials['email']);
        $key = $this->throttleKey($email, $request);
        $ipKey = $this->ipThrottleKey($request);

        // Checked first and before any user lookup or password hashing, so a
        // blocked IP costs no database or CPU work and writes one audit row
        // per window, not one per request.
        foreach ([[$ipKey, self::IP_MAX_ATTEMPTS], [$key, self::MAX_ATTEMPTS]] as [$limitKey, $max]) {
            if (RateLimiter::tooManyAttempts($limitKey, $max)) {
                $this->recordOnce('throttled|'.$limitKey, max(1, RateLimiter::availableIn($limitKey)),
                    fn () => $this->audit->record(AuditLogger::LOGIN_THROTTLED, new: ['email' => $email]));

                throw new ThrottleRequestsException(
                    'Too many login attempts. Please try again later.',
                    headers: ['Retry-After' => RateLimiter::availableIn($limitKey)],
                );
            }
        }

        $user = User::firstWhere('email', $email);
        $reason = $this->failureReason($user, $credentials['password']);

        if ($reason !== null) {
            RateLimiter::hit($key, self::DECAY_SECONDS);
            RateLimiter::hit($ipKey, self::IP_DECAY_SECONDS);
            // At most one row per email + IP per minute (the throttle itself
            // still counts every attempt).
            $this->recordOnce('failed|'.$key, self::AUDIT_FAILED_WINDOW_SECONDS,
                fn () => $this->audit->record(AuditLogger::LOGIN_FAILED, $user, $user, new: ['email' => $email, 'reason' => $reason]));

            throw ValidationException::withMessages(['email' => self::FAILED_MESSAGE]);
        }

        RateLimiter::clear($key);

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        if (Hash::needsRehash($user->password)) {
            $user->password = $credentials['password'];
        }
        $user->last_login_at = now();
        $user->save();

        $this->audit->record(AuditLogger::LOGIN, $user, $user);

        return redirect()->intended(route('admin.dashboard'));
    }

    /**
     * POST only (a GET cannot sign anyone out).
     */
    public function logout(Request $request): RedirectResponse
    {
        if ($user = $request->user()) {
            $this->audit->record(AuditLogger::LOGOUT, $user, $user);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    /**
     * Why the attempt fails, for the audit log only; null when it succeeds.
     * Unknown emails still pay the cost of hashing, so response time doesn't
     * reveal whether the account exists.
     */
    private function failureReason(?User $user, string $password): ?string
    {
        if ($user === null) {
            Hash::make($password);

            return 'unknown_email';
        }

        if (! Hash::check($password, $user->password)) {
            return 'invalid_password';
        }

        return $user->status === UserStatus::Active ? null : 'inactive';
    }

    /**
     * Runs $record unless one already ran for $key inside the window.
     * Cache::add() is atomic, so concurrent requests cannot both pass.
     */
    private function recordOnce(string $key, int $seconds, callable $record): void
    {
        if (Cache::add('audit-once|'.hash('sha256', $key), true, $seconds)) {
            $record();
        }
    }

    private function ipThrottleKey(Request $request): string
    {
        return 'admin-login-ip|'.$request->ip();
    }

    private function throttleKey(string $email, Request $request): string
    {
        return 'admin-login|'.Str::transliterate($email).'|'.$request->ip();
    }
}
