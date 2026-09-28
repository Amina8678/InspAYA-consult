<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level authorization (NFR-SEC-06/08): `permission:posts.create`,
 * `permission:users.view,users.update` to require every listed permission,
 * or `permission:services.edit|services.manage` to require any one of the
 * alternatives. Guests are sent to the login page; signed-in users without
 * the permission get 403.
 */
class EnsureUserHasPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user() ?? throw new AuthenticationException;
        $gate = Gate::forUser($user);

        foreach ($permissions as $permission) {
            abort_unless($gate->any(explode('|', $permission)), 403);
        }

        return $next($request);
    }
}
