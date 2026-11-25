<?php

namespace Tests\Feature;

use App\Models\FeeBillIdentity;
use App\Models\FeeHead;
use App\Models\FeeReceipt;
use App\Models\FeeStructure;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Documents the confirmed duplicate-billing rule and its concurrency gap.
 *
 * Billing identity = (student_id, academic_year, fee_head_id, period):
 * student_id + academic_year live on fee_receipts; fee_head_id + period live
 * on fee_receipt_items. A bill is a duplicate when a matching item exists
 * under a receipt that is neither refunded nor soft-deleted.
 *
 * NOTE ON CONCURRENCY: these tests run on in-memory SQLite, which serializes
 * writes. They verify the deterministic application-level rule only. They do
 * NOT reproduce the real time-of-check/time-of-use race between two
 * concurrent HTTP requests, which requires MySQL InnoDB (see the report).
 */
class FeeBillingDeduplicationTest extends TestCase
{
    use RefreshDatabase;

    private const YEAR = '2026-2027';

    private function accountant(): User
    {
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role' => 'accountant']);

        return $user;
    }

    private function student(string $adm, ?int $classId = null): Student
    {
        return Student::create([
            'first_name' => 'Bill',
            'last_name' => 'Student',
            'gender' => 'male',
            'dob' => '2014-05-01',
            'class_applied' => 'Grade 6',
            'adm_number' => $adm,
            'status' => 'active',
            'school_class_id' => $classId,
        ]);
    }

    private function feeHead(string $name): FeeHead
    {
        return FeeHead::create(['name' => $name, 'frequency' => 'monthly', 'is_active' => true]);
    }

    private function storePayload(Student $student, FeeHead $head, string $period = 'Baisakh', float $amount = 500): array
    {
        return [
            'student_id' => $student->id,
            'academic_year' => self::YEAR,
            'receipt_date' => now()->toDateString(),
            'items' => [[
                'fee_head_id' => $head->id,
                'description' => $head->name,
                'period' => $period,
                'amount' => $amount,
            ]],
        ];
    }

    private function receiptCountFor(Student $student, FeeHead $head, string $period): int
    {
        return FeeReceipt::where('student_id', $student->id)
            ->where('academic_year', self::YEAR)
            ->whereHas('items', fn ($q) => $q->where('fee_head_id', $head->id)->where('period', $period))
            ->count();
    }

    /** Confirmed rule: a repeated manual billing request is rejected by the existing convention. */
    public function test_manual_store_rejects_the_same_logical_bill(): void
    {
        $accountant = $this->accountant();
        $student = $this->student('ADM-DUP-1');
        $head = $this->feeHead('Tuition');

        $this->actingAs($accountant)
            ->post(route('admin.fees.store'), $this->storePayload($student, $head))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(1, $this->receiptCountFor($student, $head, 'Baisakh'));

        $this->actingAs($accountant)
            ->post(route('admin.fees.store'), $this->storePayload($student, $head))
            ->assertSessionHasErrors('items');

        $this->assertSame(1, $this->receiptCountFor($student, $head, 'Baisakh'));
    }

    /** Different legitimate fee heads are billed independently. */
    public function test_different_fee_heads_for_same_student_and_period_are_allowed(): void
    {
        $accountant = $this->accountant();
        $student = $this->student('ADM-HEADS');
        $tuition = $this->feeHead('Tuition');
        $transport = $this->feeHead('Transport');

        $this->actingAs($accountant)
            ->post(route('admin.fees.store'), $this->storePayload($student, $tuition))
            ->assertSessionHas('success');

        $this->actingAs($accountant)
            ->post(route('admin.fees.store'), $this->storePayload($student, $transport))
            ->assertSessionHas('success');

        $this->assertSame(1, $this->receiptCountFor($student, $tuition, 'Baisakh'));
        $this->assertSame(1, $this->receiptCountFor($student, $transport, 'Baisakh'));
        $this->assertSame(2, FeeReceipt::where('student_id', $student->id)->count());
    }

    /** Different legitimate periods for same student and fee head are allowed. */
    public function test_different_periods_for_same_student_and_head_are_allowed(): void
    {
        $accountant = $this->accountant();
        $student = $this->student('ADM-PERIODS');
        $head = $this->feeHead('Tuition');

        $this->actingAs($accountant)
            ->post(route('admin.fees.store'), $this->storePayload($student, $head, 'Baisakh'))
            ->assertSessionHas('success');

        $this->actingAs($accountant)
            ->post(route('admin.fees.store'), $this->storePayload($student, $head, 'Jestha'))
            ->assertSessionHas('success');

        $this->assertSame(1, $this->receiptCountFor($student, $head, 'Baisakh'));
        $this->assertSame(1, $this->receiptCountFor($student, $head, 'Jestha'));
    }

    /** A soft-deleted receipt is not "already billed" and may be re-billed. */
    public function test_soft_deleted_receipt_does_not_count_as_already_billed(): void
    {
        $accountant = $this->accountant();
        $student = $this->student('ADM-SOFT');
        $head = $this->feeHead('Tuition');

        $this->actingAs($accountant)
            ->post(route('admin.fees.store'), $this->storePayload($student, $head))
            ->assertSessionHas('success');

        $receipt = FeeReceipt::where('student_id', $student->id)->firstOrFail();
        $receipt->delete();

        $this->assertFalse(FeeReceipt::alreadyBilled($student->id, self::YEAR, $head->id, 'Baisakh'));

        $this->actingAs($accountant)
            ->post(route('admin.fees.store'), $this->storePayload($student, $head))
            ->assertSessionHas('success');

        $this->assertSame(1, FeeReceipt::where('student_id', $student->id)->count());
        $this->assertSame(2, FeeReceipt::withTrashed()->where('student_id', $student->id)->count());
    }

    /** A refunded receipt is not "already billed" and may be re-billed. */
    public function test_refunded_receipt_does_not_count_as_already_billed(): void
    {
        $accountant = $this->accountant();
        $student = $this->student('ADM-REFUND');
        $head = $this->feeHead('Tuition');

        // Bill with an immediate payment so it can be refunded.
        $payload = $this->storePayload($student, $head);
        $payload['paying_now'] = 500;
        $payload['mode'] = 'cash';

        $this->actingAs($accountant)
            ->post(route('admin.fees.store'), $payload)
            ->assertSessionHas('success');

        $receipt = FeeReceipt::where('student_id', $student->id)->firstOrFail();

        $this->actingAs($accountant)
            ->post(route('admin.fees.refund', $receipt), ['reason' => 'Transferred'])
            ->assertSessionHas('success');

        $this->assertFalse(FeeReceipt::alreadyBilled($student->id, self::YEAR, $head->id, 'Baisakh'));

        $this->actingAs($accountant)
            ->post(route('admin.fees.store'), $this->storePayload($student, $head))
            ->assertSessionHas('success');

        // Two live-capable receipts now exist (the refunded one and the new one).
        $this->assertSame(2, FeeReceipt::where('student_id', $student->id)->count());
    }

    /** Bulk generation follows the same rule and skips a student already billed through the manual path. */
    public function test_generate_skips_bill_created_by_manual_store(): void
    {
        $accountant = $this->accountant();
        $class = SchoolClass::create(['name' => 'Grade 6']);
        $student = $this->student('ADM-CROSS', $class->id);
        $head = $this->feeHead('Tuition');

        FeeStructure::create([
            'academic_year' => self::YEAR,
            'school_class_id' => $class->id,
            'fee_head_id' => $head->id,
            'amount' => 500,
        ]);

        // Manual bill first.
        $this->actingAs($accountant)
            ->post(route('admin.fees.store'), $this->storePayload($student, $head))
            ->assertSessionHas('success');

        $this->assertSame(1, $this->receiptCountFor($student, $head, 'Baisakh'));

        // Bulk generation for the same period must not create a second bill.
        $this->actingAs($accountant)
            ->post(route('admin.fee-structure.generate'), [
                'academic_year' => self::YEAR,
                'class_id' => $class->id,
                'period' => 'Baisakh',
                'head_ids' => [$head->id],
                'receipt_date' => now()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertSame(1, $this->receiptCountFor($student, $head, 'Baisakh'));
    }

    /**
     * End-to-end: once a living bill exists, a repeated store is rejected and
     * leaves exactly one live bill and one identity. The DB-level test below
     * proves the unique constraint is the authoritative guard independent of
     * the application's pre-check; here we confirm the whole request path
     * (pre-check + constraint) preserves the invariant.
     *
     * NOTE: this is sequential on SQLite. True concurrent interleaving is not
     * reproducible here; it requires MySQL InnoDB (see the report).
     */
    public function test_repeated_store_after_a_live_bill_keeps_one_identity(): void
    {
        $accountant = $this->accountant();
        $student = $this->student('ADM-RACE');
        $head = $this->feeHead('Tuition');

        // A living bill created through the real claim mechanism.
        $this->manualReceiptWithIdentity($student, $head);
        $this->assertSame(1, FeeBillIdentity::count());

        $this->actingAs($accountant)
            ->post(route('admin.fees.store'), $this->storePayload($student, $head))
            ->assertSessionHasErrors('items');

        $this->assertSame(1, $this->receiptCountFor($student, $head, 'Baisakh'));
        $this->assertSame(1, FeeBillIdentity::count());
    }

    /** A direct duplicate identity insert is rejected by the DB constraint. */
    public function test_unique_constraint_is_the_authoritative_guard(): void
    {
        $student = $this->student('ADM-CONSTRAINT');
        $head = $this->feeHead('Tuition');

        $first = $this->manualReceiptWithIdentity($student, $head);

        $this->assertSame(1, FeeBillIdentity::count());

        $second = FeeReceipt::create([
            'receipt_no' => 'RCP-2026-88888888',
            'student_id' => $student->id,
            'academic_year' => self::YEAR,
            'receipt_date' => now()->toDateString(),
            'subtotal' => 500,
            'discount' => 0,
            'fine' => 0,
            'total_due' => 500,
            'paid_amount' => 0,
            'balance' => 500,
            'status' => 'unpaid',
        ]);
        $item = $second->items()->create([
            'fee_head_id' => $head->id,
            'description' => $head->name,
            'period' => 'Baisakh',
            'amount' => 500,
        ]);

        $threw = false;
        try {
            DB::transaction(function () use ($second, $item) {
                $second->claimIdentities(collect([$item]));
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            $threw = true;
        }

        $this->assertTrue($threw, 'The duplicate identity claim must violate the unique constraint.');
        $this->assertSame(1, FeeBillIdentity::count());
        $this->assertSame($first->id, FeeBillIdentity::first()->fee_receipt_id);
    }

    private function manualReceiptWithIdentity(Student $student, FeeHead $head): FeeReceipt
    {
        $receipt = FeeReceipt::create([
            'receipt_no' => 'RCP-2026-77777777',
            'student_id' => $student->id,
            'academic_year' => self::YEAR,
            'receipt_date' => now()->toDateString(),
            'subtotal' => 500,
            'discount' => 0,
            'fine' => 0,
            'total_due' => 500,
            'paid_amount' => 0,
            'balance' => 500,
            'status' => 'unpaid',
        ]);
        $receipt->items()->create([
            'fee_head_id' => $head->id,
            'description' => $head->name,
            'period' => 'Baisakh',
            'amount' => 500,
        ]);
        $receipt->claimIdentities($receipt->items()->get());

        return $receipt;
    }

    /** Failed creation rolls back and leaves no partial bill. */
    public function test_failed_creation_rolls_back_receipt_and_items(): void
    {
        $accountant = $this->accountant();
        $student = $this->student('ADM-ROLLBACK');
        $head = $this->feeHead('Tuition');

        FeeReceipt::created(function () {
            throw new \RuntimeException('forced failure');
        });

        try {
            $this->actingAs($accountant)
                ->withoutExceptionHandling()
                ->post(route('admin.fees.store'), $this->storePayload($student, $head));
            $this->fail('Expected the forced failure to propagate.');
        } catch (\RuntimeException $e) {
            $this->assertSame('forced failure', $e->getMessage());
        } finally {
            FeeReceipt::flushEventListeners();
        }

        $this->assertSame(0, FeeReceipt::withTrashed()->where('student_id', $student->id)->count());
    }
}
