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
        // The guards also recover safely from a previously interrupted local
        // development migration. They never inspect or change pre-Phase 6
        // tables; only these new, additive translation tables are considered.
        if (! Schema::hasTable('public_content_translations')) {
            Schema::create('public_content_translations', function (Blueprint $table): void {
                $table->id();
                // Content families are an explicit, application-validated allowlist.
                // English remains in its existing typed source tables.
                $table->string('content_type', 60);
                $table->unsignedBigInteger('content_id');
                $table->enum('locale', ['ne'])->default('ne');
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

                $table->unique(['content_type', 'content_id', 'locale'], 'public_content_translation_locale_unique');
                $table->index(
                    ['content_type', 'content_id', 'locale', 'publication_state', 'scheduled_for'],
                    'public_content_translation_visibility_index',
                );
            });
        }

        $contentRevisionTableAlreadyExists = Schema::hasTable('public_content_translation_revisions');
        if (! $contentRevisionTableAlreadyExists) {
            Schema::create('public_content_translation_revisions', function (Blueprint $table): void {
                $table->id();
                // MySQL caps identifiers at 64 characters, so these explicit
                // compact names are intentional and stable.
                $table->unsignedBigInteger('public_content_translation_id');
                $table->foreign('public_content_translation_id', 'pctrev_translation_fk')
                    ->references('id')->on('public_content_translations')->cascadeOnDelete();
                $table->unsignedInteger('revision_number');
                // Revisions stay traceable to the English source snapshot they translate.
                $table->unsignedBigInteger('source_revision_id')->nullable()->index();
                $table->string('source_fingerprint', 64)->nullable()->index();
                // Payload shape is validated by content-family services; it is never
                // used as a generic public page-builder document.
                $table->json('payload')->nullable();
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

                $table->unique(
                    ['public_content_translation_id', 'revision_number'],
                    'public_content_translation_revision_number',
                );
                $table->index(
                    ['public_content_translation_id', 'workflow_state'],
                    'public_content_translation_revision_state',
                );
            });
        }

        if ($contentRevisionTableAlreadyExists) {
            Schema::table('public_content_translation_revisions', function (Blueprint $table): void {
                $table->foreign('public_content_translation_id', 'pctrev_translation_fk')
                    ->references('id')->on('public_content_translations')->cascadeOnDelete();
            });
        }

        Schema::table('public_content_translations', function (Blueprint $table): void {
            $table->foreign('published_revision_id', 'pct_published_revision_fk')
                ->references('id')
                ->on('public_content_translation_revisions')
                ->nullOnDelete();
            $table->foreign('scheduled_revision_id', 'pct_scheduled_revision_fk')
                ->references('id')
                ->on('public_content_translation_revisions')
                ->nullOnDelete();
        });

        if (! Schema::hasTable('public_content_translation_media')) {
            Schema::create('public_content_translation_media', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('public_content_translation_revision_id');
                $table->foreign('public_content_translation_revision_id', 'pctmedia_revision_fk')
                    ->references('id')->on('public_content_translation_revisions')->cascadeOnDelete();
                // Localized text annotates a source-owned Media Library placement;
                // it never creates another asset, derivative, or original path.
                $table->unsignedBigInteger('media_attachment_id');
                $table->foreign('media_attachment_id', 'pctmedia_attachment_fk')
                    ->references('id')->on('media_attachments')->cascadeOnDelete();
                $table->string('alt_text', 255)->nullable();
                $table->text('caption')->nullable();
                $table->timestamps();

                $table->unique(
                    ['public_content_translation_revision_id', 'media_attachment_id'],
                    'public_content_translation_media_attachment_unique',
                );
            });
        }

        if (! Schema::hasTable('public_site_translations')) {
            Schema::create('public_site_translations', function (Blueprint $table): void {
                $table->id();
                // These are fixed singleton scopes, not arbitrary settings or pages.
                $table->enum('scope', ['homepage', 'identity']);
                $table->enum('locale', ['ne'])->default('ne');
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

                $table->unique(['scope', 'locale'], 'public_site_translation_scope_locale_unique');
                $table->index(
                    ['scope', 'locale', 'publication_state', 'scheduled_for'],
                    'public_site_translation_visibility_index',
                );
            });
        }

        $siteRevisionTableAlreadyExists = Schema::hasTable('public_site_translation_revisions');
        if (! $siteRevisionTableAlreadyExists) {
            Schema::create('public_site_translation_revisions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('public_site_translation_id');
                $table->foreign('public_site_translation_id', 'pstrev_translation_fk')
                    ->references('id')->on('public_site_translations')->cascadeOnDelete();
                $table->unsignedInteger('revision_number');
                $table->string('source_fingerprint', 64)->nullable()->index();
                $table->json('payload')->nullable();
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

                $table->unique(
                    ['public_site_translation_id', 'revision_number'],
                    'public_site_translation_revision_number',
                );
                $table->index(
                    ['public_site_translation_id', 'workflow_state'],
                    'public_site_translation_revision_state',
                );
            });
        }

        if ($siteRevisionTableAlreadyExists) {
            Schema::table('public_site_translation_revisions', function (Blueprint $table): void {
                $table->foreign('public_site_translation_id', 'pstrev_translation_fk')
                    ->references('id')->on('public_site_translations')->cascadeOnDelete();
            });
        }

        Schema::table('public_site_translations', function (Blueprint $table): void {
            $table->foreign('published_revision_id', 'pst_published_revision_fk')
                ->references('id')
                ->on('public_site_translation_revisions')
                ->nullOnDelete();
            $table->foreign('scheduled_revision_id', 'pst_scheduled_revision_fk')
                ->references('id')
                ->on('public_site_translation_revisions')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('public_site_translations', function (Blueprint $table): void {
            $table->dropForeign('pst_published_revision_fk');
            $table->dropForeign('pst_scheduled_revision_fk');
        });

        Schema::dropIfExists('public_site_translation_revisions');
        Schema::dropIfExists('public_site_translations');
        Schema::dropIfExists('public_content_translation_media');

        Schema::table('public_content_translations', function (Blueprint $table): void {
            $table->dropForeign('pct_published_revision_fk');
            $table->dropForeign('pct_scheduled_revision_fk');
        });

        Schema::dropIfExists('public_content_translation_revisions');
        Schema::dropIfExists('public_content_translations');
    }
};
