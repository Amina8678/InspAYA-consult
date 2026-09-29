<?php

use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ForceHttps;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // There is no route named `login`; guests hitting admin routes go to
        // the admin login page instead of erroring.
        $middleware->redirectGuestsTo(fn () => route('admin.login'));
        $middleware->redirectUsersTo(fn () => route('admin.dashboard'));

        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'permission' => EnsureUserHasPermission::class,
        ]);

        // TrustProxies is already in Laravel's default global middleware
        // stack; by default it trusts nothing, so $request->secure() reflects
        // the raw connection instead of an X-Forwarded-Proto header from a
        // reverse proxy/load balancer. Left unset, that's the safe default
        // (see ForceHttps). A production deployment that terminates TLS at a
        // proxy must set TRUSTED_PROXIES (see docs/admin-auth.md) — this
        // never trusts "*" (any proxy) unless that env var is explicitly set
        // to "*", which is a deliberate, documented deployment choice, not a
        // default.
        $middleware->trustProxies(
            at: blank($trustedProxies = env('TRUSTED_PROXIES')) ? null : $trustedProxies,
        );

        // NFR-SEC-09: on every response, public and admin alike — including a
        // 404 for a URL that matches no route at all, which never enters the
        // 'web' group's middleware, so this is global instead. `prepend()`
        // puts its argument at the very front of the *entire* global
        // middleware list (ahead of Laravel's own default entries, which is
        // where TrustProxies normally lives) — so TrustProxies must be named
        // here too, first, or ForceHttps would read $request->secure() before
        // TrustProxies has resolved the forwarded scheme, defeating
        // TRUSTED_PROXIES above regardless of how it's configured. Then
        // SecurityHeaders wraps ForceHttps, so it still runs its "after" step
        // (adding the headers) even when ForceHttps short-circuits with a
        // redirect. ForceHttps is a no-op outside production, so local dev and
        // tests (both http) are unaffected.
        $middleware->prepend([
            TrustProxies::class,
            SecurityHeaders::class,
            ForceHttps::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
