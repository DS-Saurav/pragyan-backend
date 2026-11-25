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
        Schema::create('admission_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('label', 120);
            $table->string('public_status', 32)->default('contact_us');
            $table->text('public_intro')->nullable();
            $table->date('display_starts_on')->nullable();
            $table->date('display_ends_on')->nullable();
            $table->string('publication_state', 32)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->string('public_phone', 50)->nullable();
            $table->string('public_email')->nullable();
            $table->string('primary_action_label', 60)->nullable();
            $table->string('primary_action_url')->nullable();
            $table->text('fee_summary')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['publication_state', 'display_starts_on', 'display_ends_on'], 'admission_cycles_public_window_index');
        });

        Schema::create('admission_cycle_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_cycle_id')->constrained()->cascadeOnDelete();
            $table->string('grade', 120);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['admission_cycle_id', 'sort_order']);
        });

        Schema::create('admission_cycle_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_cycle_id')->constrained()->cascadeOnDelete();
            $table->string('title', 160);
            $table->text('body')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['admission_cycle_id', 'sort_order']);
        });

        Schema::create('admission_cycle_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_cycle_id')->constrained()->cascadeOnDelete();
            $table->string('label', 160);
            $table->text('detail')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['admission_cycle_id', 'sort_order']);
        });

        Schema::create('admission_cycle_faqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_cycle_id')->constrained()->cascadeOnDelete();
            $table->string('question', 255);
            $table->text('answer');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['admission_cycle_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admission_cycle_faqs');
        Schema::dropIfExists('admission_cycle_requirements');
        Schema::dropIfExists('admission_cycle_steps');
        Schema::dropIfExists('admission_cycle_grades');
        Schema::dropIfExists('admission_cycles');
    }
};
