<?php

namespace App\Support;

/**
 * URLs for the committed static assets (public/css, public/js,
 * public/admin-assets) with a version query string, so a browser or CDN that
 * caches them for a long time fetches the new file as soon as it changes.
 * There is no build step to fingerprint file names, so the file's
 * modification time is the version. A missing file gets a plain URL.
 */
class Assets
{
    public static function url(string $path): string
    {
        $path = ltrim($path, '/');
        $file = public_path($path);
        $mtime = is_file($file) ? filemtime($file) : false;

        return asset($path).($mtime === false ? '' : '?v='.base_convert((string) $mtime, 10, 36));
    }
}
