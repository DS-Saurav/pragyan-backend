<?php

namespace App\Console\Commands;

use App\Models\FeeBillIdentity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillFeeBillIdentities extends Command
{
    protected $signature = 'fees:backfill-bill-identities {--dry-run : Report what would be inserted without writing}';

    protected $description = 'Create billing-identity rows for existing active fee receipts (student, year, fee head, period).';

    /**
     * Backfill the fee_bill_identities table from existing data.
     *
     * A billing identity is active only for line items that are fully
     * identified (non-null fee_head_id AND period) on a receipt that is neither
     * soft-deleted nor refunded. Partial lines receive no identity, matching the
     * application's duplicate rule.
     *
     * The command is transactional and idempotent: it skips lines that already
     * have an identity. If two active lines claim the SAME identity, it reports
     * them and stops without writing anything. It never deletes, merges, or
     * rewrites receipts, items, or payments.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $lines = DB::table('fee_receipt_items as i')
            ->join('fee_receipts as r', 'r.id', '=', 'i.fee_receipt_id')
            ->whereNull('r.deleted_at')
            ->where('r.status', '!=', 'refunded')
            ->whereNotNull('i.fee_head_id')
            ->whereNotNull('i.period')
            ->select([
                'i.id as item_id',
                'i.fee_head_id',
                'i.period',
                'r.id as receipt_id',
                'r.student_id',
                'r.academic_year',
            ])
            ->orderBy('i.id')
            ->get();

        if ($lines->isEmpty()) {
            $this->info('No active, fully identified fee lines found. Nothing to backfill.');

            return self::SUCCESS;
        }

        // Detect duplicate active identities before touching the database.
        $duplicates = $lines
            ->groupBy(fn ($l) => $l->student_id.'|'.$l->academic_year.'|'.$l->fee_head_id.'|'.$l->period)
            ->filter(fn ($group) => $group->count() > 1);

        if ($duplicates->isNotEmpty()) {
            $this->error('Duplicate active billing identities detected. No rows were written.');
            $this->newLine();

            foreach ($duplicates as $key => $group) {
                [$student, $year, $head, $period] = explode('|', $key);
                $this->line(sprintf(
                    '  student_id=%s academic_year=%s fee_head_id=%s period=%s -> item ids: %s',
                    $student,
                    $year,
                    $head,
                    $period,
                    $group->pluck('item_id')->implode(', ')
                ));
            }

            $this->newLine();
            $this->warn('Resolve these duplicates manually (refund or delete the extra receipt) and re-run. Financial records were not changed.');

            return self::FAILURE;
        }

        $existingItemIds = FeeBillIdentity::pluck('fee_receipt_item_id')->all();
        $existingItemIds = array_flip($existingItemIds);

        $pending = $lines->reject(fn ($l) => isset($existingItemIds[$l->item_id]))->values();

        if ($pending->isEmpty()) {
            $this->info("All {$lines->count()} active identified line(s) already have a billing identity. Nothing to do.");

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info("Dry run: {$pending->count()} billing identity row(s) would be inserted (of {$lines->count()} active identified line(s)). No changes made.");

            return self::SUCCESS;
        }

        $now = now();

        DB::transaction(function () use ($pending, $now) {
            foreach ($pending->chunk(500) as $chunk) {
                $rows = $chunk->map(fn ($l) => [
                    'student_id' => $l->student_id,
                    'academic_year' => $l->academic_year,
                    'fee_head_id' => $l->fee_head_id,
                    'period' => $l->period,
                    'fee_receipt_id' => $l->receipt_id,
                    'fee_receipt_item_id' => $l->item_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                FeeBillIdentity::insert($rows);
            }
        });

        $this->info("Backfill complete: inserted {$pending->count()} billing identity row(s) for {$lines->count()} active identified line(s).");

        return self::SUCCESS;
    }
}
