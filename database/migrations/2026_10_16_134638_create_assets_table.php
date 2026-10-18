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
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_tag')->unique(); // e.g., LIB-0001, ROB-0001
            $table->string('name');                // e.g., "Python Crash Course" or "ESP32 NodeMCU"
            $table->string('category');            // library, robotics, electronics, chemistry, physics, general
            $table->string('location');            // e.g., Library Shelf 2, Science Lab
            $table->date('purchase_date')->nullable();
            $table->decimal('cost', 12, 2)->default(0);
            $table->integer('quantity')->default(1);
            $table->integer('available_quantity')->default(1);
            $table->foreignId('borrowed_by_student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->integer('maintenance_logs_count')->default(0);
            $table->decimal('maintenance_cost', 10, 2)->default(0);
            $table->enum('status', ['in_use', 'maintenance', 'retired', 'issued'])->default('in_use');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
