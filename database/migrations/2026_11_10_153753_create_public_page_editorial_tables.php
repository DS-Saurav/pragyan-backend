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
        Schema::create('public_pages', function (Blueprint $table) {
            $table->id();
            // Fixed, typed destinations rather than arbitrary CMS pages.
            $table->string('type', 20)->unique();
            $table->string('publication_state', 20)->default('draft')->index();
            $table->unsignedBigInteger('published_revision_id')->nullable()->index();
            $table->unsignedBigInteger('scheduled_revision_id')->nullable()->index();
            $table->timestamp('scheduled_for')->nullable()->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('withdrawn_at')->nullable()->index();
            $table->timestamp('archived_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'publication_state', 'scheduled_for', 'published_at'], 'public_pages_visibility_index');
        });

        Schema::create('public_page_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('public_page_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision_number');
            $table->string('title', 180);
            // Concrete editorial fields for the two approved page types,
            // deliberately not a generic block/page-builder structure.
            $table->longText('introduction_markdown')->nullable();
            $table->longText('history_markdown')->nullable();
            $table->longText('mission_markdown')->nullable();
            $table->longText('vision_markdown')->nullable();
            $table->json('approach_values')->nullable();
            $table->longText('facilities_markdown')->nullable();
            $table->string('workflow_state', 20)->default('draft')->index();
            $table->foreignId('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('published_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['public_page_id', 'revision_number'], 'public_page_revision_number');
            $table->index(['public_page_id', 'workflow_state'], 'public_page_revision_state');
        });

        Schema::table('public_pages', function (Blueprint $table) {
            $table->foreign('published_revision_id')
                ->references('id')
                ->on('public_page_revisions')
                ->nullOnDelete();
            $table->foreign('scheduled_revision_id')
                ->references('id')
                ->on('public_page_revisions')
                ->nullOnDelete();
        });

        Schema::create('public_academic_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('public_page_revision_id')->constrained()->cascadeOnDelete();
            $table->string('title', 180);
            $table->string('stage_label', 100)->nullable();
            $table->longText('description_markdown')->nullable();
            $table->json('key_points')->nullable();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();

            $table->unique(['public_page_revision_id', 'display_order'], 'public_academic_stage_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('public_academic_stages');

        Schema::table('public_pages', function (Blueprint $table) {
            $table->dropForeign(['published_revision_id']);
            $table->dropForeign(['scheduled_revision_id']);
        });

        Schema::dropIfExists('public_page_revisions');
        Schema::dropIfExists('public_pages');
    }
};
