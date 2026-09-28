<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Account status control (NFR-SEC-02): a user deactivated while signed in is
 * signed out on their next request instead of keeping their session.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->status !== UserStatus::Active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            abort_if($request->expectsJson(), 403, 'This account is not active.');

            return redirect()->route('admin.login')
                ->withErrors(['email' => 'This account is not active.']);
        }

        return $next($request);
    }
}
