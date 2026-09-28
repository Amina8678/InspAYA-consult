<?php

namespace App\View\Presenters;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;

/**
 * The only shape in which an image reaches a view: a ready-to-use absolute
 * URL plus alt text and dimensions. Views never build storage paths.
 */
class MediaPresenter
{
    /**
     * @return array{url: string, alt: string, width: ?int, height: ?int, mime_type: string}|null
     */
    public static function present(?Media $media): ?array
    {
        if ($media === null) {
            return null;
        }

        return [
            'url' => Storage::disk($media->disk)->url($media->storage_path),
            'alt' => (string) $media->alt_text,
            'width' => $media->width,
            'height' => $media->height,
            'mime_type' => $media->mime_type,
        ];
    }
}
