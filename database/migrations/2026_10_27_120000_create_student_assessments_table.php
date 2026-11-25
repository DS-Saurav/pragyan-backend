<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assessment-indicator ratings per student and examination (Stage P3I).
 *
 * - One authoritative rating row per (exam, student, indicator_key):
 *   the unique constraint plus updateOrCreate keep saves idempotent.
 * - `indicator_key` is the STABLE machine identity of the indicator as
 *   configured in the marksheet layout editor (e.g. "handwriting"), NOT
 *   its mutable display label - renaming a label can never misassign a
 *   saved rating.
 * - `rating` holds a machine value from the configured scale
 *   (config("school.assessments.scale"), e.g. "good"); the human label is
 *   rendered at display time from that config.
 * - Rows are never auto-created: a rating the staff did not record simply
 *   does not exist, and unset stays unset.
 * - exam_id/segregation keep every examination's ratings independent, so
 *   history survives student promotion (students are updated in place).
 * - Not an academic subject grade, CDC grade point, or GPA input;
 *   deliberately a separate narrow table (ConductRegister stays untouched).
 *
 * NOTE: must be applied with explicit owner approval (php artisan
 * migrate). See the stage P3I report for migration-readiness steps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('indicator_key', 60)
                ->comment('Stable indicator key from the marksheet layout, not the mutable label');
            $table->string('rating', 30)
                ->comment('Machine rating value from config("school.assessments.scale"), e.g. good');
            $table->timestamps();

            $table->unique(['exam_id', 'student_id', 'indicator_key'], 'student_assessments_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_assessments');
    }
};
