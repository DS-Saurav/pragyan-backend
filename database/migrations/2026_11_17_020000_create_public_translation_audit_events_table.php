<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_translation_audit_events', function (Blueprint $table): void {
            $table->id();
            // The two translation roots intentionally remain distinct tables.
            // This typed reference records lifecycle history without making a
            // generic public-content model or rewriting existing source data.
            $table->enum('translation_type', ['content', 'site']);
            $table->unsignedBigInteger('translation_id');
            $table->unsignedBigInteger('revision_id')->nullable();
            $table->string('action', 40);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['translation_type', 'translation_id', 'created_at'], 'public_translation_audit_timeline');
            $table->index(['action', 'created_at'], 'public_translation_audit_action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_translation_audit_events');
    }
};
