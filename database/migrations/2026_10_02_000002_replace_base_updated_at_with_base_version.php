<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pending revisions compare against the live record's version (the one
     * the Editor's form was loaded with) instead of its updated_at. Revisions
     * that already exist have no version baseline, and so are never reported
     * as stale: the same rule that applied to revisions without a baseline
     * before this column existed.
     */
    public function up(): void
    {
        Schema::table('pending_revisions', function (Blueprint $table) {
            $table->unsignedInteger('base_version')->nullable()->after('changes');
            $table->dropColumn('base_updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('pending_revisions', function (Blueprint $table) {
            $table->timestamp('base_updated_at')->nullable()->after('changes');
            $table->dropColumn('base_version');
        });
    }
};
