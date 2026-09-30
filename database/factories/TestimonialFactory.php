<?php

namespace Database\Factories;

use App\Models\Media;
use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Testimonial>
 */
class TestimonialFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quote' => fake()->paragraph(3),
            'author_name' => fake()->name(),
            'author_title' => fake()->jobTitle(),
            'organization' => fake()->company(),
            'photo_id' => null,
            'is_active' => true,
            'sort_order' => fake()->numberBetween(1, 100),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    public function withPhoto(): static
    {
        return $this->state(fn (array $attributes) => ['photo_id' => Media::factory()]);
    }
}
