<?php

namespace Database\Factories;

use App\Enums\PostStatus;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BlogPost>
 */
class BlogPostFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = rtrim(fake()->unique()->sentence(6), '.');

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => fake()->sentence(20),
            'content' => fake()->paragraphs(5, true),
            'author_id' => User::factory(),
            'category_id' => null,
            'featured_image_id' => null,
            'status' => PostStatus::Draft,
            'published_at' => null,
            'meta_title' => $title,
            'meta_description' => fake()->sentence(12),
            'canonical_url' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PostStatus::Draft,
            'published_at' => null,
        ]);
    }

    public function inReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PostStatus::Review,
            'published_at' => null,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PostStatus::Published,
            'published_at' => fake()->dateTimeBetween('-6 months', '-1 day'),
        ]);
    }

    public function categorised(): static
    {
        return $this->state(fn (array $attributes) => ['category_id' => Category::factory()]);
    }

    public function withFeaturedImage(): static
    {
        return $this->state(fn (array $attributes) => ['featured_image_id' => Media::factory()]);
    }
}
