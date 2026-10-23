<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A counter incremented on every change to a live page or post, so two
     * people (or an Editor's stale form) editing the same record can be told
     * apart exactly, unlike updated_at's one-second resolution.
     *
     * Safe on a table with data: a NOT NULL column with a constant DEFAULT is
     * added as a metadata-only change on MySQL 8 (ALGORITHM=INSTANT), nothing
     * is rewritten, and every existing row reads as version 1.
     */
    public function up(): void
    {
        foreach (['pages', 'blog_posts'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->unsignedInteger('version')->default(1);
            });
        }
    }

    public function down(): void
    {
        foreach (['pages', 'blog_posts'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('version');
            });
        }
    }
};
