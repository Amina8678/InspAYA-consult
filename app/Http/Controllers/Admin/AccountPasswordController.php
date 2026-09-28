<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

/**
 * A signed-in user changes their own password. The current password is
 * required, the new one must meet the password policy, and every other
 * session of this user is signed out.
 */
class AccountPasswordController extends Controller
{
    public const UPDATED_MESSAGE = 'Your password has been changed.';

    public function __construct(private AuditLogger $audit) {}

    public function edit(): View
    {
        return view('admin.account.password');
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::defaults()],
        ]);

        $user = $request->user();
        $user->password = $validated['password'];
        $user->save();

        // Re-stores the hash for this session and invalidates all others
        // (enforced by the auth.session middleware on admin routes).
        Auth::logoutOtherDevices($validated['password']);
        $request->session()->regenerate();

        $this->audit->record(AuditLogger::PASSWORD_CHANGED, $user, $user);

        return redirect()->route('admin.account.password.edit')->with('status', self::UPDATED_MESSAGE);
    }
}
