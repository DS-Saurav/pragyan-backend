<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->string('member_type'); // 'Student' or 'Teacher'
            $table->string('member_name'); // e.g. "Student 1 Demo"
            $table->date('issue_date');
            $table->date('due_date');
            $table->date('returned_date')->nullable();
            $table->integer('fine')->default(0); // in currency units
            $table->string('status')->default('issued'); // 'issued', 'overdue', 'returned'
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_issues');
    }
};
