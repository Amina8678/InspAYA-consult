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
        Schema::create('service_consultant', function (Blueprint $table) {
            // CASCADE both sides: an assignment dies with its service or
            // consultant.
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consultant_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_lead')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->primary(['service_id', 'consultant_id']);
            $table->index('consultant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_consultant');
    }
};
