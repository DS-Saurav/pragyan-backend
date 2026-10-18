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
        Schema::table('exam_subjects', function (Blueprint $table) {
            if (! Schema::hasColumn('exam_subjects', 'start_time')) {
                $table->time('start_time')->nullable()->after('exam_date');
            }
            if (! Schema::hasColumn('exam_subjects', 'end_time')) {
                $table->time('end_time')->nullable()->after('start_time');
            }
        });

        Schema::table('students', function (Blueprint $table) {
            if (! Schema::hasColumn('students', 'photo')) {
                $table->string('photo')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('exam_subjects', function (Blueprint $table) {
            $table->dropColumn(['start_time', 'end_time']);
        });
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('photo');
        });
    }
};
