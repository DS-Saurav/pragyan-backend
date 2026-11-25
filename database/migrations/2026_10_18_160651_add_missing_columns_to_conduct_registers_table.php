<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conduct_registers', function (Blueprint $table) {
            if (! Schema::hasColumn('conduct_registers', 'student_id')) {
                $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            }
            if (! Schema::hasColumn('conduct_registers', 'recorded_by')) {
                $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
            }
            if (! Schema::hasColumn('conduct_registers', 'entry_date')) {
                $table->date('entry_date');
            }
            if (! Schema::hasColumn('conduct_registers', 'type')) {
                $table->enum('type', ['merit', 'demerit']);
            }
            if (! Schema::hasColumn('conduct_registers', 'points')) {
                $table->integer('points')->default(0);
            }
            if (! Schema::hasColumn('conduct_registers', 'note')) {
                $table->text('note');
            }
        });
    }

    public function down(): void
    {
        Schema::table('conduct_registers', function (Blueprint $table) {
            $table->dropColumn(['student_id', 'recorded_by', 'entry_date', 'type', 'points', 'note']);
        });
    }
};
