<?php

namespace App\Support;

/**
 * Escaping stops HTML injection but not a `javascript:` link in an href.
 * CMS-entered URLs (consultant links, page call-to-actions, social links)
 * pass through here before reaching a view: only http(s), mailto, tel and
 * site-relative URLs survive; anything else becomes null.
 */
class SafeUrl
{
    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    public static function sanitize(mixed $url): ?string
    {
        if (! is_string($url)) {
            return null;
        }

        // Browsers ignore control characters and whitespace inside schemes
        // ("java\tscript:"), so strip them before checking.
        $url = trim($url);
        $compact = (string) preg_replace('/[\x00-\x20\x7F]+/', '', $url);

        if ($compact === '') {
            return null;
        }

        // Site-relative: "/path", "#anchor", "?query" (but not "//host").
        if (preg_match('~^(/(?!/)|#|\?)~', $compact)) {
            return $url;
        }

        $scheme = strtolower((string) parse_url($compact, PHP_URL_SCHEME));

        return in_array($scheme, self::ALLOWED_SCHEMES, true) ? $url : null;
    }
}
