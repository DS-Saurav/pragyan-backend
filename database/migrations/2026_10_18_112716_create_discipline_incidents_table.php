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
        Schema::create('discipline_incidents', function (Blueprint $table) {
            $table->id();

            // People involved (allowed values for everything below live on the
            // DisciplineIncident model: CATEGORIES, SEVERITIES, ACTIONS, NOTIFY_METHODS, STATUSES)
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('teachers')->nullOnDelete();

            // What happened
            $table->date('incident_date');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location', 120)->nullable();
            $table->string('witnesses')->nullable();

            // Classification
            $table->string('category'); // DisciplineIncident::CATEGORIES
            $table->string('severity', 20)->default('medium'); // DisciplineIncident::SEVERITIES
            $table->string('status', 20)->default('open'); // DisciplineIncident::STATUSES

            // Consequence
            $table->string('action_type')->nullable(); // DisciplineIncident::ACTIONS
            $table->text('action_taken')->nullable();

            // Parent / guardian follow-up
            $table->boolean('parent_notified')->default(false);
            $table->date('parent_notified_on')->nullable();
            $table->string('notified_via')->nullable(); // DisciplineIncident::NOTIFY_METHODS
            $table->date('follow_up_date')->nullable();

            // Resolution
            $table->text('resolution')->nullable();
            $table->dateTime('closed_at')->nullable();

            $table->timestamps();

            // Status tab filters/counts and incident-date ranges + ordering
            $table->index('incident_date');
            $table->index(['status', 'incident_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discipline_incidents');
    }
};
