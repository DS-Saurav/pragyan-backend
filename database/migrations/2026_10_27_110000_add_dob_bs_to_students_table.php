<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Student date of birth in the Bikram Sambat (B.S.) calendar (Stage P3H).
 *
 * Stored as a plain nullable string (max 20 chars, e.g. "2069-01-15"),
 * deliberately NOT a `date` column and NOT cast to Carbon in the model:
 * a B.S. date must never be read, sorted or formatted as a Gregorian
 * (A.D.) date by the database or the framework.
 *
 * - Independent of the existing A.D. `dob` column: saving one never
 *   rewrites the other.
 * - Nullable: existing students without a recorded B.S. date stay valid
 *   and render an explicit "unavailable" state on the marksheet.
 * - No automatic A.D.<->B.S. conversion is performed anywhere (the app
 *   has no verified conversion dependency - see the stage P3H report).
 *
 * NOTE: must be applied with explicit owner approval (php artisan
 * migrate). See the stage P3H report for migration-readiness steps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('dob_bs', 20)->nullable()->after('dob')
                ->comment("Date of birth in Bikram Sambat (B.S.), e.g. 2069-01-15; null = not recorded");
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('dob_bs');
        });
    }
};
