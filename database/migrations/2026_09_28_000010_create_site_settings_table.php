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
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('group', 50)->index();
            $table->string('type', 20)->default('string');
            $table->text('value')->nullable();
            // Used when type = media (logo, footer images). SET NULL: removing
            // the file blanks the setting rather than deleting it.
            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->timestamps();

            $table->index('media_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
