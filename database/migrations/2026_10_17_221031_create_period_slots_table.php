<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('period_slots', function (Blueprint $table) {
            $table->id();
            $table->string('period_code'); // e.g. P1, P2, RECESS
            $table->string('name'); // e.g. First Period, Recess
            $table->time('start_time'); // e.g. 08:30:00
            $table->time('end_time'); // e.g. 09:15:00
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('period_slots');
    }
};
