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
        // A content edit by someone without content.publish, made to an
        // already-published Page or BlogPost, waiting for an Administrator/
        // Super Admin to apply or discard it (stage 16). Polymorphic rather
        // than one table per model: it's the same workflow either way, and
        // this app already trusts exactly this shape for audit_logs (an
        // entity_type/entity_id pair plus a JSON field-value map).
        Schema::create('pending_revisions', function (Blueprint $table) {
            $table->id();
            $table->string('revisable_type');
            $table->unsignedBigInteger('revisable_id');
            // SET NULL: the revision (and its audit trail) survives the
            // submitting Editor's account going away.
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            // Proposed field => new value, same shape as audit_logs.new_values.
            // Diffed against the live record's current values at review time,
            // since the live record cannot change while this is pending.
            $table->json('changes');
            $table->string('status', 20)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['revisable_type', 'revisable_id']);
            $table->index('status');
            $table->index('submitted_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pending_revisions');
    }
};
