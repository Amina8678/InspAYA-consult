<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `email.from_address` was seeded and editable but nothing ever read it: mail
 * is sent from MAIL_FROM_ADDRESS. Delete the dead row so databases seeded
 * earlier lose it too (it can no longer be edited in the CMS, and its
 * example.com value would otherwise trip `launch:check` with no way to fix it).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('site_settings')->where('key', 'email.from_address')->delete();
    }

    /**
     * Nothing to restore: the setting had no effect.
     */
    public function down(): void {}
};
