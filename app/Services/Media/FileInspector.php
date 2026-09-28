<?php

namespace App\Services\Media;

use finfo;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Upload rules (NFR-SEC-10). The type is decided by the file's bytes
 * (libmagic via finfo, plus getimagesize for images and the %PDF- signature
 * for PDFs), never by the client's MIME type or file name. The SRS names no
 * types or limits; these are the proposed defaults (see docs/admin-media.md).
 */
class FileInspector
{
    private const MB = 1024 * 1024;

    /**
     * Whitelist: detected MIME type => stored extension, size cap, image?
     * SVG is deliberately absent (it can carry script).
     *
     * @var array<string, array{extension: string, max_bytes: int, image: bool, image_type?: int}>
     */
    public const TYPES = [
        'image/jpeg' => ['extension' => 'jpg', 'max_bytes' => 5 * self::MB, 'image' => true, 'image_type' => IMAGETYPE_JPEG],
        'image/png' => ['extension' => 'png', 'max_bytes' => 5 * self::MB, 'image' => true, 'image_type' => IMAGETYPE_PNG],
        'image/webp' => ['extension' => 'webp', 'max_bytes' => 5 * self::MB, 'image' => true, 'image_type' => IMAGETYPE_WEBP],
        'image/gif' => ['extension' => 'gif', 'max_bytes' => 5 * self::MB, 'image' => true, 'image_type' => IMAGETYPE_GIF],
        'application/pdf' => ['extension' => 'pdf', 'max_bytes' => 10 * self::MB, 'image' => false],
    ];

    /** Longest side and total pixels, against decompression bombs. */
    public const MAX_DIMENSION = 10_000;

    public const MAX_PIXELS = 40_000_000;

    public const TYPE_ERROR = 'This file type is not allowed. Upload a JPEG, PNG, WebP or GIF image, or a PDF.';

    /**
     * @return array{mime_type: string, extension: string, image: bool, size: int, width: ?int, height: ?int}
     *
     * @throws ValidationException
     */
    public function inspect(UploadedFile $file, string $field = 'file'): array
    {
        if (! $file->isValid()) {
            $this->fail($field, 'The file could not be uploaded. It may be larger than the server allows.');
        }

        $path = $file->getRealPath();
        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($path);
        $rules = self::TYPES[$mime] ?? null;

        if ($rules === null) {
            $this->fail($field, self::TYPE_ERROR);
        }

        $size = (int) $file->getSize();
        if ($size <= 0 || $size > $rules['max_bytes']) {
            $this->fail($field, sprintf('This file is too large. The limit for this type is %d MB.', $rules['max_bytes'] / self::MB));
        }

        $width = $height = null;

        if ($rules['image']) {
            $info = @getimagesize($path);

            // Must decode as the same image type that finfo detected.
            if ($info === false || $info[2] !== $rules['image_type']) {
                $this->fail($field, self::TYPE_ERROR);
            }

            [$width, $height] = $info;

            if ($width < 1 || $height < 1 || max($width, $height) > self::MAX_DIMENSION || $width * $height > self::MAX_PIXELS) {
                $this->fail($field, sprintf(
                    'This image is too large. Images can be at most %s pixels on each side.',
                    number_format(self::MAX_DIMENSION),
                ));
            }
        } elseif (file_get_contents($path, false, null, 0, 5) !== '%PDF-') {
            $this->fail($field, self::TYPE_ERROR);
        }

        return [
            'mime_type' => $mime,
            'extension' => $rules['extension'],
            'image' => $rules['image'],
            'size' => $size,
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * @return never
     */
    private function fail(string $field, string $message): void
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
