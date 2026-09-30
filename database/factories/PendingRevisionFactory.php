<?php

namespace Database\Factories;

use App\Enums\PendingRevisionStatus;
use App\Models\Page;
use App\Models\PendingRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PendingRevision>
 */
class PendingRevisionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // The enforced morph map alias ('page' — see AppServiceProvider),
            // not the FQCN: revisable_type is a plain fillable attribute here,
            // not set through the relation, so it isn't translated for us.
            'revisable_type' => (new Page)->getMorphClass(),
            'revisable_id' => Page::factory(),
            'submitted_by' => User::factory()->withRole('editor'),
            'changes' => ['title' => fake()->sentence(3)],
            'status' => PendingRevisionStatus::Pending,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PendingRevisionStatus::Approved,
            'reviewed_by' => User::factory()->withRole('administrator'),
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PendingRevisionStatus::Rejected,
            'reviewed_by' => User::factory()->withRole('administrator'),
            'reviewed_at' => now(),
            'rejection_reason' => fake()->sentence(),
        ]);
    }
}
