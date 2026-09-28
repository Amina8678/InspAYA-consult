<?php

namespace Database\Factories;

use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Rows only: no file is written to disk.
 *
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'disk' => 'public',
            'file_name' => fake()->slug(3).'.jpg',
            'storage_path' => 'media/'.now()->format('Y/m').'/'.Str::uuid().'.jpg',
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(20_000, 2_000_000),
            'width' => 1600,
            'height' => 900,
            'alt_text' => fake()->sentence(6),
            'caption' => null,
            'uploader_id' => User::factory(),
        ];
    }

    /**
     * A non-image upload (no dimensions).
     */
    public function pdf(): static
    {
        return $this->state(fn (array $attributes) => [
            'file_name' => fake()->slug(3).'.pdf',
            'storage_path' => 'media/'.now()->format('Y/m').'/'.Str::uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'width' => null,
            'height' => null,
            'alt_text' => null,
        ]);
    }

    /**
     * No recorded uploader (e.g. the account was removed).
     */
    public function withoutUploader(): static
    {
        return $this->state(fn (array $attributes) => [
            'uploader_id' => null,
        ]);
    }
}
