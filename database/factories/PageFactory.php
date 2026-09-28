<?php

namespace Database\Factories;

use App\Enums\PageStatus;
use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = Str::title(fake()->unique()->words(3, true));

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'status' => PageStatus::Draft,
            'structured_content' => [
                ['type' => 'hero', 'data' => ['heading' => $title, 'background_media_id' => null]],
                ['type' => 'text', 'data' => ['body' => fake()->paragraph()]],
            ],
            'meta_title' => $title,
            'meta_description' => fake()->sentence(12),
            'canonical_url' => null,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PageStatus::Published,
            'published_at' => now()->subDay(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PageStatus::Draft,
            'published_at' => null,
        ]);
    }
}
