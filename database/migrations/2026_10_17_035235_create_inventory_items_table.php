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
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category'); // e.g., stationery, housekeeping, office
            $table->integer('quantity')->default(0); // Current stock in hand
            $table->integer('reorder_level')->default(5); // Minimum threshold for low stock alerts
            $table->string('unit')->default('pieces'); // e.g., pack, box, litre, pieces
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
