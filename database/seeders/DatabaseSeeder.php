<?php

namespace Database\Seeders;

use Database\Seeders\Demo\BlogSeeder;
use Database\Seeders\Demo\ConsultantSeeder;
use Database\Seeders\Demo\CoreValueSeeder;
use Database\Seeders\Demo\PageSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database. Every seeder is idempotent.
     */
    public function run(): void
    {
        // Required in every environment. Order matters: the Super Admin needs
        // its role.
        $this->call([
            RolesAndPermissionsSeeder::class,
            SuperAdminSeeder::class,
            SiteSettingsSeeder::class,
            ServiceSeeder::class,
        ]);

        // Placeholder demo content, never in production. Consultants need
        // services; posts need the Super Admin as author.
        if (! app()->isProduction()) {
            $this->call([
                CoreValueSeeder::class,
                PageSeeder::class,
                ConsultantSeeder::class,
                BlogSeeder::class,
            ]);
        }
    }
}
