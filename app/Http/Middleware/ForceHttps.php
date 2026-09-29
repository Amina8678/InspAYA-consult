<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirects http to https in production only (NFR-SEC-09), so local dev and
 * the test suite — both plain http — are unaffected. If the app sits behind
 * a reverse proxy or load balancer that terminates TLS, `$request->secure()`
 * only reflects the real scheme once that proxy is trusted (see
 * config/trustedproxy or `Request::setTrustedProxies` and
 * docs/admin-auth.md); this middleware alone does not configure that.
 */
class ForceHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('production') && ! $request->secure()) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
