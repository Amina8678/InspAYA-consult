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

];
