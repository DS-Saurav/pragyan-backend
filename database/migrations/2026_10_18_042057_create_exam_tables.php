<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 30)->default('unit_test');
            $table->string('academic_year', 20);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('scheduled'); // scheduled | ongoing | completed | published
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['academic_year', 'status']);
        });

        Schema::create('exam_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->date('exam_date')->nullable();
            $table->decimal('theory_full', 6, 2)->default(100);
            $table->decimal('theory_pass', 6, 2)->default(35);
            $table->decimal('practical_full', 6, 2)->default(0);
            $table->decimal('practical_pass', 6, 2)->default(0);
            $table->string('marks_status', 20)->default('pending'); // pending | draft | submitted | locked
            $table->timestamps();

            $table->unique(['exam_id', 'school_class_id', 'subject_id']);
        });

        Schema::create('exam_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_subject_id')->constrained('exam_subjects')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->decimal('theory_marks', 6, 2)->nullable();
            $table->decimal('practical_marks', 6, 2)->nullable();
            $table->boolean('is_absent')->default(false);
            $table->timestamps();

            $table->unique(['exam_subject_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_marks');
        Schema::dropIfExists('exam_subjects');
        Schema::dropIfExists('exams');
    }
};
