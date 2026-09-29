<?php

use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ForceHttps;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
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

        // NFR-SEC-09: on every response, public and admin alike — including a
        // 404 for a URL that matches no route at all, which never enters the
        // 'web' group's middleware, so this is global instead. SecurityHeaders
        // is listed first, so it wraps ForceHttps and still runs its "after"
        // step (adding the headers) even when ForceHttps short-circuits with a
        // redirect. ForceHttps is a no-op outside production, so local dev and
        // tests (both http) are unaffected.
        $middleware->prepend([
            SecurityHeaders::class,
            ForceHttps::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
