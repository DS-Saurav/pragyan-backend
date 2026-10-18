<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->string('specialization')->nullable();
            $table->string('class_teacher_of')->nullable(); // e.g. "Class 1 A (2024-2025)"
            $table->string('username')->nullable()->unique(); // e.g. "@teacher1"
            $table->json('assignments')->nullable(); // Stores assigned classes and subjects
        });
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn(['specialization', 'class_teacher_of', 'username', 'assignments']);
        });
    }
};
