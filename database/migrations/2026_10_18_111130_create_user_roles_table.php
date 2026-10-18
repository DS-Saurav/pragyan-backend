<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 40);
            $table->timestamps();

            $table->unique(['user_id', 'role']);
        });

        // Everyone who can log in today is an admin, so nobody gets locked out.
        $now = now();
        foreach (DB::table('users')->pluck('id') as $id) {
            DB::table('user_roles')->insert(['user_id' => $id, 'role' => 'admin', 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};
