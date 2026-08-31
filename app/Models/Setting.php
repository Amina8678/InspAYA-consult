<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get(string $key, $default = null)
    {
        $all = Cache::rememberForever('settings.all', fn () => static::pluck('value', 'key'));

        if (! $all instanceof \Illuminate\Support\Collection) {
            Cache::forget('settings.all');
            $all = static::pluck('value', 'key');
            Cache::forever('settings.all', $all);
        }

        return $all[$key] ?? $default;
    }

    public static function set(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('settings.all');
    }
}