<?php

namespace App\Services\Media;

use App\Models\Media;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Stores, replaces and deletes media files (FR-ADM-09, NFR-SEC-10).
 *
 * Stored names are random (media/YYYY/MM/<uuid>.<ext>) with the extension
 * taken from the detected type, so a file can never be saved as .php, .html
 * or .svg whatever it was called. The client's file name is kept only as a
 * display label.
 */
class MediaLibrary
{
    public const DISK = 'public';

    public function __construct(private FileInspector $inspector, private MediaUsage $usage) {}

    /**
     * @param  array{alt_text?: ?string, caption?: ?string}  $meta
     *
     * @throws ValidationException
     */
    public function store(UploadedFile $file, array $meta, User $uploader): Media
    {
        $info = $this->inspector->inspect($file);
        $this->requireAltText($info, $meta['alt_text'] ?? null);

        $path = $this->put($file, $info['extension']);

        try {
            $media = new Media([
                'disk' => self::DISK,
                'file_name' => $this->displayName($file),
                'storage_path' => $path,
                'mime_type' => $info['mime_type'],
                'size' => $info['size'],
                'width' => $info['width'],
                'height' => $info['height'],
                'alt_text' => $meta['alt_text'] ?? null,
                'caption' => $meta['caption'] ?? null,
            ]);
            $media->uploader()->associate($uploader);
            $media->save();
        } catch (\Throwable $e) {
            Storage::disk(self::DISK)->delete($path);

            throw $e;
        }

        return $media;
    }

    /**
     * Update alt text and caption, optionally swapping the file. The media id
     * (and therefore every usage) stays the same.
     *
     * @param  array{alt_text?: ?string, caption?: ?string}  $meta
     *
     * @throws ValidationException
     */
    public function update(Media $media, array $meta, ?UploadedFile $replacement = null): Media
    {
        $info = $replacement ? $this->inspector->inspect($replacement) : ['image' => str_starts_with($media->mime_type, 'image/')];
        $this->requireAltText($info, $meta['alt_text'] ?? null);

        $media->fill([
            'alt_text' => $meta['alt_text'] ?? null,
            'caption' => $meta['caption'] ?? null,
        ]);

        $oldPath = null;
        if ($replacement) {
            $oldPath = $media->storage_path;
            $media->fill([
                'file_name' => $this->displayName($replacement),
                'storage_path' => $this->put($replacement, $info['extension']),
                'mime_type' => $info['mime_type'],
                'size' => $info['size'],
                'width' => $info['width'],
                'height' => $info['height'],
            ]);
        }

        $media->save();

        if ($oldPath !== null) {
            Storage::disk($media->disk)->delete($oldPath);
        }

        return $media;
    }

    /**
     * Clears every usage (plan §3.6), deletes the row, then the file.
     *
     * @return list<array{type: string, id: int, label: string, field: string}> the usages that were cleared
     */
    public function delete(Media $media): array
    {
        $usages = DB::transaction(function () use ($media) {
            $usages = $this->usage->find($media);
            $this->usage->clear($media);
            $media->delete();

            return $usages;
        });

        Storage::disk($media->disk)->delete($media->storage_path);

        return $usages;
    }

    /**
     * @param  array{image: bool}  $info
     */
    private function requireAltText(array $info, ?string $altText): void
    {
        if ($info['image'] && trim((string) $altText) === '') {
            throw ValidationException::withMessages([
                'alt_text' => 'Enter alt text describing the image (WCAG 2.2, NFR-ACC-01).',
            ]);
        }
    }

    private function put(UploadedFile $file, string $extension): string
    {
        $directory = 'media/'.now()->format('Y/m');
        $name = Str::uuid()->toString().'.'.$extension;

        $stored = Storage::disk(self::DISK)->putFileAs($directory, $file, $name);

        if ($stored === false) {
            throw ValidationException::withMessages(['file' => 'The file could not be saved. Please try again.']);
        }

        return $stored;
    }

    /**
     * The client's name, reduced to a harmless label: no directories, no
     * control characters, at most 255 characters.
     */
    private function displayName(UploadedFile $file): string
    {
        $name = basename(str_replace('\\', '/', (string) $file->getClientOriginalName()));
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', '', $name));

        return Str::limit($name === '' ? 'upload' : $name, 250, '');
    }
}
