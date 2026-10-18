<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_plans', function (Blueprint $table) {
            $table->id();

            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();

            $table->date('lesson_date');
            $table->unsignedTinyInteger('period')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();

            $table->string('unit')->nullable();
            $table->string('topic');
            $table->text('objectives')->nullable();
            $table->string('teaching_method')->nullable();
            $table->string('resources')->nullable();
            $table->string('homework')->nullable();
            $table->string('assessment')->nullable();

            $table->string('status', 20)->default('planned'); // planned | in_progress | completed | postponed
            $table->text('reflection')->nullable();

            $table->timestamps();

            $table->index(['lesson_date', 'status']);
            $table->index(['teacher_id', 'lesson_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_plans');
    }
};
