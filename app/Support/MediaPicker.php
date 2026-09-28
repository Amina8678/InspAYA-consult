<?php

namespace App\Support;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Choosing an image from the media library (never typing a path or URL).
 * Feeds the admin.partials.media-picker select and validates the chosen id.
 */
class MediaPicker
{
    /** Newest images offered in a picker; a selected older image is always included. */
    public const LIMIT = 500;

    public function __construct(private int $limit = self::LIMIT) {}

    /**
     * @param  list<int|null>  $selected  ids already chosen on the form
     * @return array<int, array{label: string, url: string, alt: string}>
     */
    public function options(array $selected = []): array
    {
        $selected = array_values(array_filter($selected));

        return Media::query()
            ->where('mime_type', 'like', 'image/%')
            ->where(fn ($q) => $q
                ->whereIn('id', Media::query()->where('mime_type', 'like', 'image/%')->latest('id')->limit($this->limit)->pluck('id'))
                ->orWhereIn('id', $selected))
            ->latest('id')
            ->get(['id', 'disk', 'storage_path', 'file_name', 'alt_text'])
            ->mapWithKeys(fn (Media $m) => [$m->id => [
                'label' => $m->file_name.($m->alt_text ? ' — '.$m->alt_text : ''),
                'url' => Storage::disk($m->disk)->url($m->storage_path),
                'alt' => (string) $m->alt_text,
            ]])
            ->all();
    }

    /**
     * Validation: the id must be an existing image in the library.
     */
    public static function rule(): Exists
    {
        return Rule::exists('media', 'id')->where(fn ($q) => $q->where('mime_type', 'like', 'image/%'));
    }
}
