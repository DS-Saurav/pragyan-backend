<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->string('day'); // Monday, Tuesday, Wednesday, Thursday, Friday
            $table->string('period'); // P1, P2, P3, P4, P5
            $table->string('time_slot')->nullable(); // e.g. 08:30 - 09:15
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->timestamps();

            // Ensure unique slot per class, day, and period
            $table->unique(['school_class_id', 'day', 'period'], 'class_day_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetables');
    }
};
