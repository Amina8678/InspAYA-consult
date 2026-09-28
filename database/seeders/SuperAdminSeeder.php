<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Creates the initial Super Admin from config('inspaya.super_admin'), which is
 * populated only from SUPER_ADMIN_* environment variables.
 *
 * Idempotent: an existing account (matched by email) keeps its password and
 * profile; only its Super Admin role and active status are enforced.
 */
class SuperAdminSeeder extends Seeder
{
    private const ENV_KEYS = [
        'name' => 'SUPER_ADMIN_NAME',
        'username' => 'SUPER_ADMIN_USERNAME',
        'email' => 'SUPER_ADMIN_EMAIL',
        'password' => 'SUPER_ADMIN_PASSWORD',
    ];

    private const MIN_PASSWORD_LENGTH = 12;

    public function run(): void
    {
        $credentials = $this->credentials();

        $role = Role::where('slug', 'super-admin')->first()
            ?? throw new RuntimeException('Super Admin role missing: run RolesAndPermissionsSeeder first.');

        $user = User::firstWhere('email', $credentials['email']);

        if ($user === null) {
            $user = new User([
                'name' => $credentials['name'],
                'username' => $credentials['username'],
                'email' => $credentials['email'],
                'password' => $credentials['password'],
            ]);
            $user->email_verified_at = now();
        }

        // Not mass assignable by design, so set explicitly.
        $user->role_id = $role->id;
        $user->status = UserStatus::Active;
        $user->save();
    }

    /**
     * @return array{name: string, username: string, email: string, password: string}
     */
    private function credentials(): array
    {
        $config = (array) config('inspaya.super_admin', []);

        $missing = array_values(array_filter(
            self::ENV_KEYS,
            fn (string $env, string $key) => blank($config[$key] ?? null),
            ARRAY_FILTER_USE_BOTH,
        ));

        if ($missing !== []) {
            throw new RuntimeException(
                'Cannot seed the Super Admin: set '.implode(', ', $missing).' in the environment (.env). '
                .'No default credentials exist by design.'
            );
        }

        if (! filter_var($config['email'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Cannot seed the Super Admin: SUPER_ADMIN_EMAIL is not a valid email address.');
        }

        if (mb_strlen($config['password']) < self::MIN_PASSWORD_LENGTH) {
            throw new RuntimeException(
                'Cannot seed the Super Admin: SUPER_ADMIN_PASSWORD must be at least '.self::MIN_PASSWORD_LENGTH.' characters.'
            );
        }

        return $config;
    }
}
