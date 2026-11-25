<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notices', function (Blueprint $table): void {
            // Legacy fields remain the public snapshot for the currently live
            // version. Editorial revisions are added separately below.
            $table->string('slug', 180)->nullable();
            $table->string('publication_state', 20)->nullable();
            $table->unsignedBigInteger('published_revision_id')->nullable();
            $table->unsignedBigInteger('scheduled_revision_id')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedSmallInteger('priority')->nullable();
            $table->boolean('is_urgent')->default(false);
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('notice_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('notice_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision_number');
            $table->string('slug', 180);
            $table->string('title', 180);
            $table->longText('content');
            $table->string('audience', 40);
            $table->date('date');
            $table->unsignedSmallInteger('priority')->nullable();
            $table->boolean('is_urgent')->default(false);
            $table->timestamp('publication_start_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('workflow_state', 20)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['notice_id', 'revision_number'], 'notice_revision_number');
            $table->index(['notice_id', 'workflow_state'], 'notice_revision_state');
        });

        Schema::create('notice_slugs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('notice_id')->constrained()->cascadeOnDelete();
            // This table is deliberately reserved for former public URLs.
            $table->string('slug', 180)->unique();
            $table->timestamps();
        });

        Schema::table('notices', function (Blueprint $table): void {
            $table->foreign('published_revision_id')
                ->references('id')
                ->on('notice_revisions')
                ->nullOnDelete();
            $table->foreign('scheduled_revision_id')
                ->references('id')
                ->on('notice_revisions')
                ->nullOnDelete();
        });

        $this->backfillPublicSlugs();

        Schema::table('notices', function (Blueprint $table): void {
            $table->unique('slug', 'notices_slug_unique');
            $table->index(
                ['publication_state', 'scheduled_for', 'published_at', 'expires_at'],
                'notices_public_visibility_index',
            );
            $table->index(
                ['is_urgent', 'publication_state', 'expires_at'],
                'notices_urgent_visibility_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('notices', function (Blueprint $table): void {
            $table->dropForeign(['published_revision_id']);
            $table->dropForeign(['scheduled_revision_id']);
        });

        Schema::dropIfExists('notice_slugs');
        Schema::dropIfExists('notice_revisions');

        Schema::table('notices', function (Blueprint $table): void {
            $table->dropUnique('notices_slug_unique');
            $table->dropIndex('notices_public_visibility_index');
            $table->dropIndex('notices_urgent_visibility_index');
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropForeign(['published_by']);
            $table->dropColumn([
                'slug',
                'publication_state',
                'published_revision_id',
                'scheduled_revision_id',
                'scheduled_for',
                'published_at',
                'expires_at',
                'priority',
                'is_urgent',
                'withdrawn_at',
                'archived_at',
                'created_by',
                'updated_by',
                'published_by',
            ]);
        });
    }

    private function backfillPublicSlugs(): void
    {
        /** @var array<string, true> $usedSlugs */
        $usedSlugs = [];

        foreach (DB::table('notices')
            ->select(['id', 'title'])
            ->where('status', 'published')
            ->whereIn('audience', ['all', 'public'])
            ->orderBy('id')
            ->cursor() as $notice) {
            $slug = $this->uniqueSlug((string) $notice->title, (int) $notice->id, $usedSlugs);

            DB::table('notices')
                ->where('id', $notice->id)
                ->update(['slug' => $slug]);

            $usedSlugs[$slug] = true;
        }
    }

    /**
     * @param  array<string, true>  $usedSlugs
     */
    private function uniqueSlug(string $title, int $id, array $usedSlugs): string
    {
        $base = Str::limit(Str::slug($title), 168, '');
        if ($base === '') {
            $base = 'notice';
        }

        if (in_array($base, ['admin', 'archive', 'create', 'edit', 'preview'], true)) {
            $base = 'notice-'.$base;
        }

        $candidate = Str::limit($base, 180, '');
        $attempt = 0;

        while (isset($usedSlugs[$candidate])
            || DB::table('notices')->where('slug', $candidate)->exists()) {
            $attempt++;
            $suffix = '-'.$id.($attempt === 1 ? '' : '-'.$attempt);
            $candidate = Str::limit($base, 180 - strlen($suffix), '').$suffix;
        }

        return $candidate;
    }
};
