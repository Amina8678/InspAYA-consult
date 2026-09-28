<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * "Forgot password" (FR-ADM-02). Always answers with the same message, so the
 * response never reveals whether an email belongs to an account. Only active
 * accounts are sent a link.
 */
class PasswordResetLinkController extends Controller
{
    public const SENT_MESSAGE = 'If an active account uses that email address, a password reset link has been sent to it.';

    public function __construct(private AuditLogger $audit) {}

    public function create(): View
    {
        return view('admin.auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);
        $email = Str::lower($validated['email']);

        $status = Password::broker()->sendResetLink(
            ['email' => $email],
            function (User $user, #[\SensitiveParameter] string $token) {
                if ($user->status !== UserStatus::Active) {
                    return 'inactive';
                }

                $user->sendPasswordResetNotification($token);

                return Password::RESET_LINK_SENT;
            },
        );

        $this->audit->record(
            AuditLogger::PASSWORD_RESET_REQUESTED,
            $user = User::firstWhere('email', $email),
            $user,
            new: ['email' => $email, 'outcome' => $status],
        );

        return back()->with('status', self::SENT_MESSAGE);
    }
}
