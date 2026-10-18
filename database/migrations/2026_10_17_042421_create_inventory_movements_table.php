<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['in', 'out', 'adjust']); // in = restock, out = dispense, adjust = correction
            $table->integer('quantity'); // amount added, removed, or adjusted
            $table->integer('balance_after'); // stock tally after this movement
            $table->string('recipient_type')->nullable(); // e.g. Teacher, Class, Department
            $table->string('recipient_name')->nullable(); // Name of teacher/class/department
            $table->string('supplier')->nullable(); // For restocks
            $table->string('bill_number')->nullable(); // For restocks
            $table->text('note')->nullable(); // Reason for adjustment or dispense details
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // Admin/Storekeeper who performed it
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
