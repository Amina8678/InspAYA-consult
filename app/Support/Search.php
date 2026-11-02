<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class Search
{
    /**
     * Operator for case-insensitive LIKE pattern matching across database engines.
     * MySQL and SQLite are case-insensitive with default collations; PostgreSQL
     * LIKE is case-sensitive, so ILIKE is used there.
     */
    public static function likeOperator(): string
    {
        return DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }

    /**
     * Escape LIKE wildcard characters (% and _ and \).
     */
    public static function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }

    /**
     * Build a "%term%" search pattern with wildcards escaped.
     */
    public static function containsPattern(string $value): string
    {
        return '%'.self::escapeLike($value).'%';
    }
}
