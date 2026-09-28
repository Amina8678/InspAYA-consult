<?php

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $group = fake()->randomElement(['users', 'content', 'posts', 'media', 'enquiries', 'settings']);
        $action = fake()->unique()->slug(2);

        return [
            'name' => Str::headline("{$group} {$action}"),
            'slug' => "{$group}.{$action}",
            'group' => $group,
            'description' => fake()->sentence(),
        ];
    }
}
