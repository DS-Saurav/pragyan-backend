<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Drop old class_id column if it exists in your table
            if (Schema::hasColumn('students', 'class_id')) {
                $table->dropColumn('class_id');
            }
            // Add standard convention column
            $table->foreignId('school_class_id')->nullable()->constrained('school_classes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropForeign(['school_class_id']);
            $table->dropColumn('school_class_id');
        });
    }
};
