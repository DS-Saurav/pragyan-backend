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
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('adm_number')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('gender');
            $table->date('dob');
            $table->string('nationality')->nullable();
            $table->string('languages_spoken')->nullable();
            $table->string('curriculum')->default('IB PYP');
            $table->string('eal')->default('Native');
            $table->string('class_applied');
            $table->integer('roll_no')->nullable();
            $table->string('status')->default('active');
            $table->string('house')->default('Red');
            $table->date('admission_date')->nullable();
            $table->string('blood_group', 5)->nullable();
            $table->string('emergency_contact')->nullable();
            $table->string('hobbies')->nullable();
            $table->string('transport_route')->nullable();
            $table->string('previous_school')->nullable();
            $table->boolean('has_allergies')->default(false);
            $table->string('allergy_details')->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};