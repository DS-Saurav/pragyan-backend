<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Academic-year-specific student health measurements (Stage P3G).
 *
 * Students are updated in place (StudentController::update/promote
 * rewrite the same row with the new academic_year), so the students
 * table cannot preserve a measurement history. This table stores ONE
 * authoritative height/weight record per student per academic year:
 *
 * - height in centimetres, weight in kilograms (decimal(4,1): fine
 *   precision for a school health check, e.g. 132.5 cm / 34.0 kg);
 * - both nullable: a measurement may remain unset - never 0, never
 *   fabricated;
 * - unique(student_id, academic_year): the database enforces exactly
 *   one authoritative record per student per year;
 * - optional measured_on date for the health-check day;
 * - older years' rows survive promotion (StudentController::promote
 *   touches only the students row, never these records).
 *
 * NOTE: must be applied with explicit owner approval (php artisan
 * migrate). See the stage P3G report for migration-readiness steps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('academic_year', 20)->comment('Academic year this measurement belongs to');
            $table->decimal('height_cm', 4, 1)->nullable()->comment('Height in centimetres; null = not recorded');
            $table->decimal('weight_kg', 4, 1)->nullable()->comment('Weight in kilograms; null = not recorded');
            $table->date('measured_on')->nullable()->comment('Date of the health check, if recorded');
            $table->timestamps();

            $table->unique(['student_id', 'academic_year'], 'student_measurements_student_year_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_measurements');
    }
};
