<?php

namespace Database\Factories;

use App\Models\CoreValue;
use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CoreValue>
 */
class CoreValueFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = Str::title(fake()->unique()->word());

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'description' => fake()->sentence(15),
            'icon_id' => null,
            'is_active' => true,
            'sort_order' => fake()->numberBetween(1, 100),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    public function withIcon(): static
    {
        return $this->state(fn (array $attributes) => ['icon_id' => Media::factory()]);
    }
}
