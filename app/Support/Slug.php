<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Slugs: lowercase words joined by single hyphens (the same pattern the
 * public routes accept), unique per table.
 */
class Slug
{
    // D: "$" means end of string (a trailing newline must not pass).
    public const PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D';

    public const MAX = 191;

    /**
     * A unique slug from $source for $model's table: "energy-policy",
     * then "energy-policy-2", "-3", … The model itself is ignored when it
     * already exists.
     */
    public static function unique(Model $model, string $source): string
    {
        $base = Str::limit(Str::slug($source), self::MAX - 4, '');
        $base = trim($base, '-') ?: Str::lower(class_basename($model)).'-'.Str::lower(Str::random(6));

        $slug = $base;
        for ($n = 2; self::taken($model, $slug); $n++) {
            $slug = $base.'-'.$n;
        }

        return $slug;
    }

    public static function taken(Model $model, string $slug): bool
    {
        return $model->newQuery()
            ->where('slug', $slug)
            ->when($model->exists, fn ($q) => $q->whereKeyNot($model->getKey()))
            ->exists();
    }
}
