<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teaching_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('lesson_plan_id')->nullable()->constrained('lesson_plans')->nullOnDelete();

            $table->date('log_date');
            $table->unsignedTinyInteger('period')->nullable();

            $table->string('topic_covered');
            $table->string('coverage', 20)->default('completed'); // completed | partial | not_covered
            $table->string('homework_given')->nullable();

            $table->unsignedSmallInteger('students_present')->nullable();
            $table->unsignedSmallInteger('students_absent')->nullable();

            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->index(['log_date', 'school_class_id']);
            $table->index(['teacher_id', 'log_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_logs');
    }
};
