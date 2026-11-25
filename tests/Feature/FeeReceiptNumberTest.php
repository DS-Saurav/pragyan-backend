<?php

namespace Tests\Feature;

use App\Models\FeeReceipt;
use App\Models\Student;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FeeReceiptNumberTest extends TestCase
{
    use RefreshDatabase;

    private function student(string $adm = 'ADM-RCP-1'): Student
    {
        return Student::create([
            'first_name' => 'Receipt',
            'last_name' => 'Owner',
            'gender' => 'male',
            'dob' => '2014-05-01',
            'class_applied' => 'Grade 6',
            'adm_number' => $adm,
            'status' => 'active',
        ]);
    }

    private function makeReceipt(string $receiptNo, string $year = '2026-2027'): FeeReceipt
    {
        return FeeReceipt::create([
            'receipt_no' => $receiptNo,
            'student_id' => $this->student('ADM-'.uniqid())->id,
            'academic_year' => $year,
            'receipt_date' => now()->toDateString(),
            'subtotal' => 100,
            'discount' => 0,
            'fine' => 0,
            'total_due' => 100,
            'paid_amount' => 0,
            'balance' => 100,
            'status' => 'unpaid',
        ]);
    }

    public function test_next_number_starts_at_one_and_advances(): void
    {
        $year = '2026-2027';

        $this->assertSame('RCP-2026-00000001', FeeReceipt::nextNumber($year));

        $this->makeReceipt('RCP-2026-00000001', $year);

        $this->assertSame('RCP-2026-00000002', FeeReceipt::nextNumber($year));
    }

    public function test_soft_deleted_receipts_are_still_counted_to_avoid_reuse(): void
    {
        $year = '2026-2027';
        $this->makeReceipt('RCP-2026-00000001', $year);
        $r = $this->makeReceipt('RCP-2026-00000002', $year);
        $r->delete(); // soft delete

        $this->assertSame(1, FeeReceipt::count());
        $this->assertSame(2, FeeReceipt::withTrashed()->count());
        $this->assertSame('RCP-2026-00000003', FeeReceipt::nextNumber($year));
    }

    public function test_create_with_unique_number_retries_past_a_collision(): void
    {
        $year = '2026-2027';
        $taken = $this->makeReceipt('RCP-2026-00000001', $year);

        $attempts = 0;
        // Force the first attempt onto the already-taken number to simulate a
        // stale candidate (as a concurrent creator would produce). Subsequent
        // attempts use the freshly generated number.
        FeeReceipt::creating(function (FeeReceipt $receipt) use (&$attempts, $taken) {
            $attempts++;
            if ($attempts === 1) {
                $receipt->receipt_no = $taken->receipt_no;
            }
        });

        try {
            $created = FeeReceipt::createWithUniqueNumber([
                'student_id' => $this->student('ADM-RETRY')->id,
                'academic_year' => $year,
                'receipt_date' => now()->toDateString(),
                'subtotal' => 100,
                'discount' => 0,
                'fine' => 0,
                'total_due' => 100,
                'paid_amount' => 0,
                'balance' => 100,
                'status' => 'unpaid',
            ]);
        } finally {
            FeeReceipt::flushEventListeners();
        }

        $this->assertSame(2, $attempts, 'The colliding attempt should be retried once.');
        $this->assertSame('RCP-2026-00000002', $created->receipt_no);
        $this->assertSame(2, FeeReceipt::count());
        $this->assertCount(2, FeeReceipt::pluck('receipt_no')->unique());
    }

    public function test_exhausted_retries_throw_a_query_exception(): void
    {
        $year = '2026-2027';
        $taken = $this->makeReceipt('RCP-2026-00000001', $year);

        // Every attempt is forced onto the same taken number, so the bounded
        // retries run out and the collision must surface.
        FeeReceipt::creating(function (FeeReceipt $receipt) use ($taken) {
            $receipt->receipt_no = $taken->receipt_no;
        });

        $this->expectException(QueryException::class);

        try {
            FeeReceipt::createWithUniqueNumber([
                'student_id' => $this->student('ADM-EXHAUST')->id,
                'academic_year' => $year,
                'receipt_date' => now()->toDateString(),
                'subtotal' => 100,
                'discount' => 0,
                'fine' => 0,
                'total_due' => 100,
                'paid_amount' => 0,
                'balance' => 100,
                'status' => 'unpaid',
            ]);
        } finally {
            FeeReceipt::flushEventListeners();
        }
    }

    public function test_unrelated_database_errors_are_not_retried(): void
    {
        $attempts = 0;
        FeeReceipt::creating(function (FeeReceipt $receipt) use (&$attempts) {
            $attempts++;
            // A NOT NULL / integrity error unrelated to receipt_no uniqueness.
            $receipt->student_id = null;
        });

        try {
            $this->expectException(QueryException::class);

            FeeReceipt::createWithUniqueNumber([
                'student_id' => $this->student('ADM-OTHER')->id,
                'academic_year' => '2026-2027',
                'receipt_date' => now()->toDateString(),
                'subtotal' => 100,
                'discount' => 0,
                'fine' => 0,
                'total_due' => 100,
                'paid_amount' => 0,
                'balance' => 100,
                'status' => 'unpaid',
            ]);
        } finally {
            FeeReceipt::flushEventListeners();
            // Only one attempt: a non-number integrity error must not be retried.
            $this->assertSame(1, $attempts);
        }
    }

    public function test_create_with_unique_number_runs_after_create_inside_transaction(): void
    {
        $year = '2026-2027';
        $baselineLevel = DB::transactionLevel();
        $sawReceipt = null;

        $receipt = FeeReceipt::createWithUniqueNumber([
            'student_id' => $this->student('ADM-AFTER')->id,
            'academic_year' => $year,
            'receipt_date' => now()->toDateString(),
            'subtotal' => 100,
            'discount' => 0,
            'fine' => 0,
            'total_due' => 100,
            'paid_amount' => 0,
            'balance' => 100,
            'status' => 'unpaid',
        ], function (FeeReceipt $created) use (&$sawReceipt) {
            $sawReceipt = $created;
            $created->items()->create([
                'description' => 'Tuition Fee',
                'period' => 'Baisakh',
                'amount' => 100,
            ]);
        });

        $this->assertSame($receipt->id, $sawReceipt->id);
        $this->assertSame(1, $receipt->items()->count());
        $this->assertSame($baselineLevel, DB::transactionLevel(), 'The helper transaction should have committed.');
    }

    public function test_after_create_failure_rolls_back_the_receipt(): void
    {
        $year = '2026-2027';

        try {
            $this->expectException(\RuntimeException::class);

            FeeReceipt::createWithUniqueNumber([
                'student_id' => $this->student('ADM-RB')->id,
                'academic_year' => $year,
                'receipt_date' => now()->toDateString(),
                'subtotal' => 100,
                'discount' => 0,
                'fine' => 0,
                'total_due' => 100,
                'paid_amount' => 0,
                'balance' => 100,
                'status' => 'unpaid',
            ], function () {
                throw new \RuntimeException('after-create failure');
            });
        } finally {
            // The receipt created inside the transaction must be rolled back.
            $this->assertSame(0, FeeReceipt::count());
        }
    }
}
