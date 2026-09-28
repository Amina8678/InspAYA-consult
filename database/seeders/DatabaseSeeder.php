<?php

namespace Database\Seeders;

use Database\Seeders\Demo\BlogSeeder;
use Database\Seeders\Demo\ConsultantSeeder;
use Database\Seeders\Demo\PageSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database. Every seeder is idempotent.
     *
     * In production, never rerun after roles have been edited in the CMS:
     * RolesAndPermissionsSeeder resets role permissions to its matrix (see
     * schema plan, Deployment notes).
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
            CoreValueSeeder::class,
        ]);

        if (app()->isProduction()) {
            // Empty draft shells only; editors add approved content.
            $this->call(PageShellSeeder::class);

            return;
        }

        // Placeholder demo content, never in production. Consultants need
        // services; posts need the Super Admin as author.
        $this->call([
            PageSeeder::class,
            ConsultantSeeder::class,
            BlogSeeder::class,
        ]);
    }
}
