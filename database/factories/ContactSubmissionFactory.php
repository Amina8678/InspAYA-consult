<?php

namespace Database\Factories;

use App\Enums\EnquiryStatus;
use App\Models\ContactSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fictional data only: example.* emails, 555-01xx phone numbers (reserved for
 * fiction) and 192.0.2.0/24 addresses (reserved for documentation, RFC 5737).
 *
 * @extends Factory<ContactSubmission>
 */
class ContactSubmissionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->numerify('+1 555 01##'),
            'organization' => fake()->company(),
            'subject' => fake()->sentence(5),
            'message' => fake()->paragraph(),
            'status' => EnquiryStatus::New,
            'assigned_to' => null,
            'consent_at' => now(),
            'responded_at' => null,
            'ip_address' => '192.0.2.'.fake()->numberBetween(1, 254),
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => ['status' => EnquiryStatus::InProgress]);
    }

    public function responded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EnquiryStatus::Responded,
            'responded_at' => now(),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => EnquiryStatus::Closed]);
    }

    public function assignedTo(?User $user = null): static
    {
        return $this->state(fn (array $attributes) => [
            'assigned_to' => $user?->id ?? User::factory(),
        ]);
    }
}
