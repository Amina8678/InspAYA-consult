<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Colours are stored only as #RGB or #RRGGBB, so a stored value can never
 * carry CSS syntax (";", "}", "url(", "</style>") into a page.
 */
class HexColour implements ValidationRule
{
    // D: "$" means end of string (a trailing newline must not pass).
    public const PATTERN = '/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/D';

    public static function isValid(mixed $value): bool
    {
        return is_string($value) && preg_match(self::PATTERN, $value) === 1;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::isValid($value)) {
            $fail('The :attribute must be a hex colour like #0A1E38 or #FFF.');
        }
    }
}
