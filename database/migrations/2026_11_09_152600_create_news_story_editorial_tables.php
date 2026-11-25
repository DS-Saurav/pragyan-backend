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
        Schema::create('news_stories', function (Blueprint $table) {
            $table->id();
            // This is only changed when a replacement revision becomes public.
            $table->string('slug', 180)->unique();
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

            $table->index(['publication_state', 'scheduled_for', 'published_at'], 'news_stories_public_index');
        });

        Schema::create('news_story_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_story_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision_number');
            $table->string('slug', 180);
            $table->string('title', 180);
            $table->string('summary', 500);
            $table->longText('body_markdown');
            $table->string('category', 80)->nullable()->index();
            $table->string('public_attribution', 255)->nullable();
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

            $table->unique(['news_story_id', 'revision_number'], 'news_story_revision_number');
            $table->index(['news_story_id', 'workflow_state'], 'news_story_revision_state');
        });

        Schema::table('news_stories', function (Blueprint $table) {
            $table->foreign('published_revision_id')
                ->references('id')
                ->on('news_story_revisions')
                ->nullOnDelete();
            $table->foreign('scheduled_revision_id')
                ->references('id')
                ->on('news_story_revisions')
                ->nullOnDelete();
        });

        Schema::create('news_story_slugs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_story_id')->constrained()->cascadeOnDelete();
            // Only formerly-public canonical slugs are recorded here.
            $table->string('slug', 180)->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('news_story_slugs');

        Schema::table('news_stories', function (Blueprint $table) {
            $table->dropForeign(['published_revision_id']);
            $table->dropForeign(['scheduled_revision_id']);
        });

        Schema::dropIfExists('news_story_revisions');
        Schema::dropIfExists('news_stories');
    }
};
