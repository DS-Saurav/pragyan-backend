<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('conduct_registers', function (Blueprint $table) {
            if (!Schema::hasColumn('conduct_registers', 'type')) {
                $table->enum('type', ['merit', 'demerit'])->default('merit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('conduct_registers', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};