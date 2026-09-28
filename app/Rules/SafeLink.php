<?php

namespace App\Rules;

use App\Support\SafeUrl;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Links typed into the CMS: only http(s) (with a host), mailto:, tel: and
 * site-relative URLs pass. Anything else (javascript:, data:, //host, …) is
 * rejected at validation; SafeUrl also filters again on output.
 *
 * `new SafeLink(webOnly: true)` limits a field to absolute http(s) URLs
 * (e.g. social profiles).
 */
class SafeLink implements ValidationRule
{
    public function __construct(private bool $webOnly = false) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $url = is_string($value) ? trim($value) : null;
        $safe = SafeUrl::sanitize($url);
        $scheme = strtolower((string) parse_url((string) $url, PHP_URL_SCHEME));

        $valid = match (true) {
            $safe === null || strlen((string) $url) > 500 => false,
            in_array($scheme, ['http', 'https'], true) => filter_var($url, FILTER_VALIDATE_URL) !== false && parse_url($url, PHP_URL_HOST),
            $this->webOnly => false,
            $scheme === 'mailto' => filter_var(substr($url, 7), FILTER_VALIDATE_EMAIL) !== false,
            $scheme === 'tel' => (bool) preg_match('/^tel:\+?[0-9().\-\s]{3,30}$/', $url),
            default => true, // site-relative (SafeUrl already rejected "//host")
        };

        if (! $valid) {
            $fail($this->webOnly
                ? 'The :attribute must be a full web address starting with https:// or http://.'
                : 'The :attribute must be a web address (https://…), an email link (mailto:…), a phone link (tel:…) or a path on this site (/…).');
        }
    }
}
