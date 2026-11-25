<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CDC Letter Grading Directive, 2083: credit hours ("पाठ्यघण्टा (क्रेडिट
 * आवर)", dafa 1(घ): 32 teaching hours per credit hour) are the data source
 * for the owner-directed weighted GPA = Σ(CH × GP) / ΣCH on marksheets.
 *
 * Nullable so it can be applied harmlessly before any value is entered;
 * ResultService treats a null (or non-positive) credit hour as "no
 * authoritative data" and falls back to the honest unweighted GPA basis.
 *
 * NOTE: must be applied with explicit owner approval (php artisan migrate).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_subjects', function (Blueprint $table) {
            $table->decimal('credit_hours', 5, 2)->nullable()
                ->after('practical_pass')
                ->comment('CDC credit hours (1 credit hour = 32 teaching hours); null = not set');
        });
    }

    public function down(): void
    {
        Schema::table('exam_subjects', function (Blueprint $table) {
            $table->dropColumn('credit_hours');
        });
    }
};
