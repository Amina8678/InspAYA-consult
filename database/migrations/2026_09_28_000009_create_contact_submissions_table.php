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
        Schema::create('contact_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->index();
            $table->string('phone', 50)->nullable();
            $table->string('organization')->nullable();
            $table->string('subject');
            $table->text('message');
            $table->string('status', 20)->default('new');
            // SET NULL: the enquiry stays in the inbox, unassigned, if the
            // staff account is removed.
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            // NOT NULL: no recorded consent (FR-CONT-02), no stored submission.
            $table->timestamp('consent_at');
            $table->timestamp('responded_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('assigned_to');
        });

        // Internal notes (FR-CONT-04, FR-ADM-10); not listed in SRS §4.
        Schema::create('contact_submission_notes', function (Blueprint $table) {
            $table->id();
            // CASCADE: notes belong to the enquiry.
            $table->foreignId('contact_submission_id')->constrained()->cascadeOnDelete();
            // SET NULL: the note is kept if its author's account goes.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index('contact_submission_id');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_submission_notes');
        Schema::dropIfExists('contact_submissions');
    }
};
