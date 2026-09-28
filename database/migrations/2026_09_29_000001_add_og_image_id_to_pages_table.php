<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            // Per-page sharing image (NFR-SEO-04). SET NULL: deleting the
            // image falls back to the page's own section image or the site
            // default, never removes the page.
            $table->foreignId('og_image_id')->nullable()->after('canonical_url')->constrained('media')->nullOnDelete();
            $table->index('og_image_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Order matters: MySQL won't drop an index a foreign key needs, and
        // SQLite won't drop a column that an index still covers.
        Schema::table('pages', function (Blueprint $table) {
            $table->dropForeign(['og_image_id']);
            $table->dropIndex(['og_image_id']);
            $table->dropColumn('og_image_id');
        });
    }
};
