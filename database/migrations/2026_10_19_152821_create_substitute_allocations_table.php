<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('substitute_allocations', function (Blueprint $table) {
            $table->id();
            $table->date('allocation_date');
            $table->string('day_of_week'); // Monday, Tuesday, etc.
            $table->foreignId('absent_teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('substitute_teacher_id')->constrained('users')->cascadeOnDelete();
            $table->text('periods_covered'); // JSON or comma-separated list of periods
            $table->text('notes')->nullable();
            $table->enum('status', ['allocated', 'completed', 'cancelled'])->default('allocated');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('substitute_allocations');
    }
};
