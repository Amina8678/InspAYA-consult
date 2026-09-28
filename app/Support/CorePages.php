<?php

namespace App\Support;

/**
 * Pages the public site reaches by fixed route (routes/web.php). Their slugs
 * can't change, or the route would lose its content. The four core pages
 * (FR-HOME, FR-ABOUT, FR-LEGAL-01) can't be deleted; the contact page can,
 * because /contact works without it.
 */
class CorePages
{
    /** Page slug => public route name. */
    public const ROUTES = [
        'home' => 'home',
        'about' => 'about',
        'contact' => 'contact',
        'privacy-policy' => 'privacy-policy',
        'terms-of-service' => 'terms-of-service',
    ];

    public const UNDELETABLE = ['home', 'about', 'privacy-policy', 'terms-of-service'];

    public static function slugLocked(?string $slug): bool
    {
        return $slug !== null && isset(self::ROUTES[$slug]);
    }

    public static function deletable(?string $slug): bool
    {
        return ! in_array($slug, self::UNDELETABLE, true);
    }

    /**
     * Public URL of a page, or null when no route shows it.
     */
    public static function url(?string $slug): ?string
    {
        return self::slugLocked($slug) ? route(self::ROUTES[$slug]) : null;
    }
}
