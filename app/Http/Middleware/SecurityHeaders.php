<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers (NFR-SEC-09), on every response, public and admin alike.
 *
 * The CSP allows only this app's own self-hosted assets: no remote script,
 * style, font or image host is used anywhere in the codebase (verified
 * before writing this). script-src has no 'unsafe-inline': a fresh nonce
 * each request instead permits only the JSON-LD blocks this app itself
 * renders (Organization/Article/Service structured data) — every other
 * script is a same-origin file. The few inline style="white-space:
 * pre-line;" attributes that existed were moved to a CSS class so
 * style-src needs no exception either.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(16));
        View::share('cspNonce', $nonce);

        $response = $next($request);

        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self'",
            "img-src 'self'",
            "font-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]));
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        return $response;
    }
}
