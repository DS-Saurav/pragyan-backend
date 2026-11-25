<?php

namespace App\Console\Commands;

use App\Services\Gallery\GalleryPublisher;
use App\Services\News\NewsPublisher;
use App\Services\Notices\NoticePublisher;
use App\Services\PublicPages\PublicPagePublisher;
use App\Services\PublicTeam\PublicTeamProfilePublisher;
use App\Services\PublicTranslations\PublicTranslationPublisher;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ReleaseDuePublicContent extends Command
{
    private const LOCK_NAME = 'public-content:release-due';

    private const LOCK_SECONDS = 300;

    protected $signature = 'public-content:release-due';

    protected $description = 'Release approved public content that is due in Kathmandu time';

    /**
     * Release each existing scheduled public-content workflow. Admissions is
     * deliberately excluded because it currently has date-based visibility,
     * not a scheduled publication-state transition.
     */
    public function handle(
        NewsPublisher $newsPublisher,
        GalleryPublisher $galleryPublisher,
        NoticePublisher $noticePublisher,
        PublicPagePublisher $publicPagePublisher,
        PublicTeamProfilePublisher $publicTeamProfilePublisher,
        PublicTranslationPublisher $translationPublisher,
    ): int {
        $lock = Cache::lock(self::LOCK_NAME, self::LOCK_SECONDS);

        if (! $lock->get()) {
            $this->warn('Another due-public-content release is already running; no duplicate release was started.');

            return self::SUCCESS;
        }

        $now = CarbonImmutable::now('Asia/Kathmandu');
        $failures = [];

        try {
            $this->info(sprintf('Checking due public content at %s (Asia/Kathmandu).', $now->format('Y-m-d H:i:s')));

            $this->releaseWorkflow('News', function () use ($newsPublisher, $now): void {
                $newsPublisher->releaseDue($now);
            }, $failures);
            $this->releaseWorkflow('Gallery', function () use ($galleryPublisher, $now): void {
                $galleryPublisher->releaseDue($now);
            }, $failures);
            $this->releaseWorkflow('Notices', function () use ($noticePublisher, $now): void {
                $noticePublisher->releaseDue($now);
            }, $failures);
            $this->releaseWorkflow('Public Pages', function () use ($publicPagePublisher, $now): void {
                $publicPagePublisher->releaseDue($now);
            }, $failures);
            $this->releaseWorkflow('Public Team Profiles', function () use ($publicTeamProfilePublisher, $now): void {
                $publicTeamProfilePublisher->releaseDue($now);
            }, $failures);
            $this->releaseWorkflow('Nepali translations', function () use ($translationPublisher, $now): void {
                $translationPublisher->releaseDue($now);
            }, $failures);
        } finally {
            $lock->release();
        }

        if ($failures !== []) {
            $this->error('One or more public-content workflows could not be processed. Check the application log before retrying.');

            return self::FAILURE;
        }

        $this->info('Due public-content workflows were checked successfully.');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $failures
     */
    private function releaseWorkflow(string $label, callable $release, array &$failures): void
    {
        try {
            $release();
            $this->line(sprintf('%s: checked.', $label));
        } catch (Throwable $exception) {
            report($exception);
            $failures[] = $label;
            $this->error(sprintf('%s: could not be processed.', $label));
        }
    }
}
