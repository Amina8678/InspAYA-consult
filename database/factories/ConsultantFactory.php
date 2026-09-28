<?php

namespace Database\Factories;

use App\Models\Consultant;
use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Fictional people only: example.* emails and example.com profile links.
 *
 * @extends Factory<Consultant>
 */
class ConsultantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->name();

        return [
            'name' => $name,
            'title' => fake()->jobTitle(),
            'bio' => fake()->paragraph(),
            'photo_id' => null,
            'expertise' => fake()->words(3),
            'qualifications' => [fake()->sentence(4)],
            'email' => fake()->unique()->safeEmail(),
            'links' => ['linkedin' => 'https://example.com/profiles/'.Str::slug($name)],
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
