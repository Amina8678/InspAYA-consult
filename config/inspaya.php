<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Initial Super Admin
    |--------------------------------------------------------------------------
    |
    | Read by SuperAdminSeeder to create the first Super Admin account. These
    | values come only from the environment; never commit real values. The
    | seeder refuses to run if any of them is missing.
    |
    */

    'super_admin' => [
        'name' => env('SUPER_ADMIN_NAME'),
        'username' => env('SUPER_ADMIN_USERNAME'),
        'email' => env('SUPER_ADMIN_EMAIL'),
        'password' => env('SUPER_ADMIN_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | HSTS max-age (seconds)
    |--------------------------------------------------------------------------
    |
    | Sent as Strict-Transport-Security on HTTPS responses only. Starts at one
    | week so a mistake is cheap to recover from; raise it via HSTS_MAX_AGE
    | once HTTPS is confirmed stable (see docs/admin-auth.md).
    |
    */

    'hsts_max_age' => (int) env('HSTS_MAX_AGE', 604800),

];
