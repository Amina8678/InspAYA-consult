<?php

namespace Database\Factories;

use App\Enums\SettingType;
use App\Models\Media;
use App\Models\SiteSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteSetting>
 */
class SiteSettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $group = fake()->randomElement(['branding', 'contact', 'social', 'seo', 'analytics', 'email']);

        return [
            'key' => $group.'.'.fake()->unique()->slug(2),
            'group' => $group,
            'type' => SettingType::String,
            'value' => fake()->words(3, true),
            'media_id' => null,
        ];
    }

    /**
     * An image setting (logo, footer image): the file lives in media_id.
     */
    public function media(): static
    {
        return $this->state(fn (array $attributes) => [
            'group' => 'branding',
            'type' => SettingType::Media,
            'value' => null,
            'media_id' => Media::factory(),
        ]);
    }

    public function boolean(bool $value = true): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => SettingType::Boolean,
            'value' => $value ? '1' : '0',
        ]);
    }
}
