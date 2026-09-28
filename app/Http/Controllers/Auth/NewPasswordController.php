<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

/**
 * Sets a new password from an emailed reset link (FR-ADM-02). Any failure
 * (bad or expired token, unknown email, inactive account) gets the same
 * message, so the form can't be used to probe for accounts.
 */
class NewPasswordController extends Controller
{
    public const INVALID_MESSAGE = 'This password reset link is invalid or has expired.';

    public const RESET_MESSAGE = 'Your password has been reset. You can now sign in.';

    public function __construct(private AuditLogger $audit) {}

    public function create(Request $request, string $token): View
    {
        return view('admin.auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::broker()->reset(
            ['email' => Str::lower($validated['email'])] + $validated,
            function (User $user, #[\SensitiveParameter] string $password) {
                // Throwing here aborts before the token is consumed.
                if ($user->status !== UserStatus::Active) {
                    throw ValidationException::withMessages(['email' => self::INVALID_MESSAGE]);
                }

                $user->password = $password;
                $user->setRememberToken(Str::random(60));
                $user->save();

                event(new PasswordReset($user));
                $this->audit->record(AuditLogger::PASSWORD_RESET, $user, $user);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => self::INVALID_MESSAGE]);
        }

        return redirect()->route('admin.login')->with('status', self::RESET_MESSAGE);
    }
}
