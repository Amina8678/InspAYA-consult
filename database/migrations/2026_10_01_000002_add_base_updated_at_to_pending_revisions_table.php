<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The live record's updated_at when the revision was submitted, so
     * approval can tell whether the record changed in the meantime. Null for
     * revisions submitted before this column existed (treated as "unknown",
     * never as changed).
     */
    public function up(): void
    {
        Schema::table('pending_revisions', function (Blueprint $table) {
            $table->timestamp('base_updated_at')->nullable()->after('changes');
        });
    }

    public function down(): void
    {
        Schema::table('pending_revisions', function (Blueprint $table) {
            $table->dropColumn('base_updated_at');
        });
    }
};
