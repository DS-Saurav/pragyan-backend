<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ptm_meetings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('school_class_id')->nullable()->constrained('school_classes')->nullOnDelete();
            $table->date('meeting_date');
            $table->string('time_slot'); // e.g., '10:00 AM - 01:00 PM'
            $table->string('venue')->default('Classroom');
            $table->text('agenda')->nullable();
            $table->enum('status', ['scheduled', 'completed', 'cancelled'])->default('scheduled');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ptm_meetings');
    }
};
