<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
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
            'short_description' => fake()->sentence(15),
            'description' => fake()->paragraphs(3, true),
            'capabilities' => fake()->sentences(4),
            'outcomes' => fake()->sentences(3),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(1, 100),
            'meta_title' => $title,
            'meta_description' => fake()->sentence(12),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
