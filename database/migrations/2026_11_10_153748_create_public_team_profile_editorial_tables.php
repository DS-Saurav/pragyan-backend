<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_team_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('publication_state', 20)->default('draft')->index();
            // The root record only points at its live or future revision; the
            // full editorial history remains on the revision table.
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

            $table->index(['publication_state', 'scheduled_for', 'published_at'], 'public_team_profiles_public_index');
        });

        Schema::create('public_team_profile_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('public_team_profile_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision_number');
            // This link supports private employment-status verification only.
            // The public projection intentionally never receives this model.
            $table->foreignId('staff_member_id')->nullable()->index()->constrained('staff_members')->nullOnDelete();
            // Drafts may be intentionally incomplete, but publication checks
            // require all three fields before anything is public.
            $table->string('display_name', 140)->nullable();
            $table->string('public_role', 140)->nullable();
            $table->string('team_group', 40)->nullable()->index();
            $table->string('qualification', 255)->nullable();
            $table->text('bio_markdown')->nullable();
            $table->unsignedSmallInteger('display_order')->default(0);
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

            $table->unique(['public_team_profile_id', 'revision_number'], 'public_team_profile_revision_number');
            $table->index(['public_team_profile_id', 'workflow_state'], 'public_team_profile_revision_state');
        });

        Schema::table('public_team_profiles', function (Blueprint $table) {
            $table->foreign('published_revision_id')
                ->references('id')
                ->on('public_team_profile_revisions')
                ->nullOnDelete();
            $table->foreign('scheduled_revision_id')
                ->references('id')
                ->on('public_team_profile_revisions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('public_team_profiles', function (Blueprint $table) {
            $table->dropForeign(['published_revision_id']);
            $table->dropForeign(['scheduled_revision_id']);
        });

        Schema::dropIfExists('public_team_profile_revisions');
        Schema::dropIfExists('public_team_profiles');
    }
};
