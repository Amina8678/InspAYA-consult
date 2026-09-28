<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'action' => 'login',
            'entity_type' => null,
            'entity_id' => null,
            'old_values' => null,
            'new_values' => null,
            'ip_address' => '192.0.2.'.fake()->numberBetween(1, 254),
            'user_agent' => 'Mozilla/5.0 (Factory Test Agent)',
        ];
    }

    /**
     * A change recorded against a specific record.
     */
    public function forEntity(Model $entity, string $action = 'updated'): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => $action,
            'entity_type' => $entity->getMorphClass(),
            'entity_id' => $entity->getKey(),
            'old_values' => ['example' => 'before'],
            'new_values' => ['example' => 'after'],
        ]);
    }

    /**
     * An event with no authenticated actor (e.g. a failed login).
     */
    public function anonymous(string $action = 'login_failed'): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'action' => $action,
        ]);
    }
}
