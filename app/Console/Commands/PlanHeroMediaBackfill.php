<?php

namespace App\Console\Commands;

use App\Services\Media\HeroBackfillPlan;
use Illuminate\Console\Command;

class PlanHeroMediaBackfill extends Command
{
    protected $signature = 'media:hero-backfill {--apply : Reserved; disabled until D2-C approval}';

    protected $description = 'Inspect legacy heroes without writing data or files (dry-run only in D2-A)';

    public function handle(HeroBackfillPlan $planner): int
    {
        if ($this->option('apply')) {
            $this->error('Apply mode is unavailable in D2-A. Schema and D2-C owner approval are required.');

            return self::FAILURE;
        }
        $report = $planner->inspect();
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $report['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
