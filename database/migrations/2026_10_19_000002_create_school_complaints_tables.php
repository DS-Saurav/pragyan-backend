<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * New tables, so an existing `complaints` table (and its data) is left untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_complaints', function (Blueprint $t) {
            $t->id();
            $t->string('complaint_no', 20)->unique();
            $t->string('subject', 150);
            $t->text('description');
            $t->string('against', 30)->default('other');
            $t->string('against_detail', 150)->nullable(); // e.g. "Bus 3", "Mr. Sharma"
            $t->string('severity', 10)->default('medium');
            $t->string('status', 15)->default('open');
            $t->date('incident_date')->nullable();
            $t->foreignId('filed_by')->constrained('users')->cascadeOnDelete();
            $t->boolean('is_anonymous')->default(false);
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->boolean('is_escalated')->default(false);
            $t->timestamp('escalated_at')->nullable();
            $t->text('resolution_note')->nullable();
            $t->string('attachment_path')->nullable();
            $t->timestamp('last_activity_at')->nullable();
            $t->timestamp('resolved_at')->nullable();
            $t->softDeletes();
            $t->timestamps();
            $t->index(['status', 'severity']);
        });

        Schema::create('school_complaint_updates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('complaint_id')->constrained('school_complaints')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('type', 12)->default('comment'); // comment | system | resolution
            $t->text('body');
            $t->boolean('is_internal')->default(false); // staff-only
            $t->string('attachment_path')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_complaint_updates');
        Schema::dropIfExists('school_complaints');
    }
};
