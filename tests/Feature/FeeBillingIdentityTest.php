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
 * Covers the approved fee billing-identity design (B5).
 *
 * Identity = (student_id, academic_year, fee_head_id, period) and is claimed
 * only for fully identified lines (non-null fee head AND period). The DB
 * unique constraint on fee_bill_identities is the authoritative guard; it is
 * released only when a bill is refunded or soft-deleted.
 */
class FeeBillingIdentityTest extends TestCase
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
            'first_name' => 'Id',
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

    private function storePayload(Student $student, array $items): array
    {
        return [
            'student_id' => $student->id,
            'academic_year' => self::YEAR,
            'receipt_date' => now()->toDateString(),
            'items' => $items,
        ];
    }

    private function item(FeeHead $head, string $period = 'Baisakh', float $amount = 500): array
    {
        return [
            'fee_head_id' => $head->id,
            'description' => $head->name,
            'period' => $period,
            'amount' => $amount,
        ];
    }

    public function test_store_claims_one_identity_per_identified_line(): void
    {
        $student = $this->student('ADM-ID-1');
        $tuition = $this->feeHead('Tuition');
        $transport = $this->feeHead('Transport');

        $this->actingAs($this->accountant())
            ->post(route('admin.fees.store'), $this->storePayload($student, [
                $this->item($tuition),
                $this->item($transport),
            ]))
            ->assertSessionHas('success');

        $this->assertSame(2, FeeBillIdentity::count());
        $this->assertTrue(FeeBillIdentity::where('fee_head_id', $tuition->id)->where('period', 'Baisakh')->exists());
        $this->assertTrue(FeeBillIdentity::where('fee_head_id', $transport->id)->where('period', 'Baisakh')->exists());
    }

    public function test_partial_lines_receive_no_identity(): void
    {
        $student = $this->student('ADM-PARTIAL');
        $head = $this->feeHead('Tuition');

        $this->actingAs($this->accountant())
            ->post(route('admin.fees.store'), $this->storePayload($student, [
                ['fee_head_id' => null, 'description' => 'Misc', 'period' => 'Baisakh', 'amount' => 100],
                ['fee_head_id' => $head->id, 'description' => 'Tuition', 'period' => null, 'amount' => 100],
            ]))
            ->assertSessionHas('success');

        $this->assertSame(0, FeeBillIdentity::count());
        $this->assertSame(1, FeeReceipt::where('student_id', $student->id)->count());
    }

    public function test_duplicate_identified_line_within_one_receipt_is_rejected(): void
    {
        $student = $this->student('ADM-DUP-LINE');
        $head = $this->feeHead('Tuition');

        $this->actingAs($this->accountant())
            ->post(route('admin.fees.store'), $this->storePayload($student, [
                $this->item($head),
                $this->item($head),
            ]))
            ->assertSessionHasErrors('items');

        $this->assertSame(0, FeeReceipt::withTrashed()->where('student_id', $student->id)->count());
        $this->assertSame(0, FeeBillIdentity::count());
    }

    public function test_different_periods_get_distinct_identities(): void
    {
        $student = $this->student('ADM-DISTINCT-PERIOD');
        $head = $this->feeHead('Tuition');

        $this->actingAs($this->accountant())
            ->post(route('admin.fees.store'), $this->storePayload($student, [$this->item($head, 'Baisakh')]))
            ->assertSessionHas('success');

        $this->actingAs($this->accountant())
            ->post(route('admin.fees.store'), $this->storePayload($student, [$this->item($head, 'Jestha')]))
            ->assertSessionHas('success');

        $this->assertSame(2, FeeBillIdentity::count());
    }

    public function test_refund_releases_identity_and_allows_rebill(): void
    {
        $accountant = $this->accountant();
        $student = $this->student('ADM-REFUND-RELEASE');
        $head = $this->feeHead('Tuition');

        $payload = $this->storePayload($student, [$this->item($head)]);
        $payload['paying_now'] = 500;
        $payload['mode'] = 'cash';

        $this->actingAs($accountant)->post(route('admin.fees.store'), $payload)->assertSessionHas('success');
        $this->assertSame(1, FeeBillIdentity::count());

        $receipt = FeeReceipt::where('student_id', $student->id)->firstOrFail();

        $this->actingAs($accountant)
            ->post(route('admin.fees.refund', $receipt), ['reason' => 'Transferred'])
            ->assertSessionHas('success');

        // Identity released; items and payments preserved.
        $this->assertSame(0, FeeBillIdentity::count());
        $this->assertSame(1, $receipt->items()->count());
        $this->assertSame(1, $receipt->payments()->count());

        // Re-bill succeeds now.
        $this->actingAs($accountant)
            ->post(route('admin.fees.store'), $this->storePayload($student, [$this->item($head)]))
            ->assertSessionHas('success');

        $this->assertSame(1, FeeBillIdentity::count());
        $this->assertSame(2, FeeReceipt::where('student_id', $student->id)->count());
    }

    public function test_soft_delete_via_model_releases_identity_and_allows_rebill(): void
    {
        $accountant = $this->accountant();
        $student = $this->student('ADM-SOFT-RELEASE');
        $head = $this->feeHead('Tuition');

        $this->actingAs($accountant)
            ->post(route('admin.fees.store'), $this->storePayload($student, [$this->item($head)]))
            ->assertSessionHas('success');

        $receipt = FeeReceipt::where('student_id', $student->id)->firstOrFail();
        $this->assertSame(1, FeeBillIdentity::count());

        // Delete through the model directly: the deleting hook must release.
        $receipt->delete();

        $this->assertSame(0, FeeBillIdentity::count());
        $this->assertSame(0, FeeReceipt::count());
        $this->assertSame(1, FeeReceipt::withTrashed()->count());
        // Financial history preserved.
        $this->assertSame(1, $receipt->items()->count());

        $this->actingAs($accountant)
            ->post(route('admin.fees.store'), $this->storePayload($student, [$this->item($head)]))
            ->assertSessionHas('success');

        $this->assertSame(1, FeeBillIdentity::count());
    }

    public function test_controller_destroy_releases_identity(): void
    {
        $accountant = $this->accountant();
        $student = $this->student('ADM-DESTROY');
        $head = $this->feeHead('Tuition');

        $this->actingAs($accountant)
            ->post(route('admin.fees.store'), $this->storePayload($student, [$this->item($head)]))
            ->assertSessionHas('success');

        $receipt = FeeReceipt::where('student_id', $student->id)->firstOrFail();
        $id = $receipt->id;

        // Create a payment record if schema allows
        $receipt->payments()->create([
            'amount' => 100,
            'mode' => 'cash',
            'reference_no' => null,
            'paid_on' => now()->toDateString(),
            'received_by' => $accountant->id,
        ]);

        $paymentId = $receipt->payments()->first()->id;

        $this->actingAs($accountant)
            ->delete(route('admin.fees.destroy', $receipt));

        $count = \Illuminate\Support\Facades\DB::table('fee_bill_identities')->where('fee_receipt_id', $id)->count();
        $row = \Illuminate\Support\Facades\DB::table('fee_bill_identities')->first();
        $this->assertSoftDeleted('fee_receipts', ['id' => $id]);
        $this->assertSame(1, \Illuminate\Support\Facades\DB::table('fee_receipt_items')->where('fee_receipt_id', $id)->count(), 'Receipt items must be preserved');
        $this->assertSame(0, $count, 'Identities for this receipt should be released; row='.json_encode($row));
        $this->assertTrue(\Illuminate\Support\Facades\DB::table('fee_payments')->where('id', $paymentId)->exists(), 'Payment record must be preserved');
    }

    public function test_generate_claims_identities_and_skips_existing(): void
    {
        $accountant = $this->accountant();
        $class = SchoolClass::create(['name' => 'Grade 6']);
        $head = $this->feeHead('Tuition');
        $this->student('ADM-GEN-1', $class->id);
        $this->student('ADM-GEN-2', $class->id);

        FeeStructure::create([
            'academic_year' => self::YEAR,
            'school_class_id' => $class->id,
            'fee_head_id' => $head->id,
            'amount' => 500,
        ]);

        $this->actingAs($accountant)
            ->post(route('admin.fee-structure.generate'), [
                'academic_year' => self::YEAR,
                'class_id' => $class->id,
                'period' => 'Baisakh',
                'head_ids' => [$head->id],
                'receipt_date' => now()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertSame(2, FeeReceipt::count());
        $this->assertSame(2, FeeBillIdentity::count());

        // Re-running skips everyone (already billed).
        $this->actingAs($accountant)
            ->post(route('admin.fee-structure.generate'), [
                'academic_year' => self::YEAR,
                'class_id' => $class->id,
                'period' => 'Baisakh',
                'head_ids' => [$head->id],
                'receipt_date' => now()->toDateString(),
            ])
            ->assertRedirect()
            ->assertSessionHas('success', fn ($m) => str_contains($m, '2 student(s) skipped'));

        $this->assertSame(2, FeeReceipt::count());
        $this->assertSame(2, FeeBillIdentity::count());
    }

    public function test_identity_survives_normal_writes_and_only_releases_on_refund_or_delete(): void
    {
        $accountant = $this->accountant();
        $student = $this->student('ADM-PAY');
        $head = $this->feeHead('Tuition');

        $payload = $this->storePayload($student, [$this->item($head)]);
        $payload['paying_now'] = 200;
        $payload['mode'] = 'cash';

        $this->actingAs($accountant)->post(route('admin.fees.store'), $payload)->assertSessionHas('success');
        $receipt = FeeReceipt::where('student_id', $student->id)->firstOrFail();

        // A further payment must not release the identity.
        $this->actingAs($accountant)
            ->post(route('admin.fees.pay', $receipt), [
                'amount' => 100,
                'mode' => 'cash',
                'paid_on' => now()->toDateString(),
            ])
            ->assertSessionHas('success');

        $this->assertSame(1, FeeBillIdentity::count());
    }

    public function test_backfill_command_is_idempotent_and_reports_dry_run(): void
    {
        $student = $this->student('ADM-BACKFILL');
        $head = $this->feeHead('Tuition');

        // Existing receipt WITHOUT a claim (simulating pre-B5 data).
        $receipt = FeeReceipt::create([
            'receipt_no' => 'RCP-2026-00000042',
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

        $this->assertSame(0, FeeBillIdentity::count());

        // Dry run writes nothing.
        $this->artisan('fees:backfill-bill-identities --dry-run')->assertSuccessful();
        $this->assertSame(0, FeeBillIdentity::count());

        // Real run inserts one claim.
        $this->artisan('fees:backfill-bill-identities')->assertSuccessful();
        $this->assertSame(1, FeeBillIdentity::count());

        // Idempotent: running again inserts nothing.
        $this->artisan('fees:backfill-bill-identities')->assertSuccessful();
        $this->assertSame(1, FeeBillIdentity::count());
    }

    public function test_backfill_command_stops_on_duplicate_active_identities(): void
    {
        $student = $this->student('ADM-BACKFILL-DUP');
        $head = $this->feeHead('Tuition');

        foreach (['RCP-2026-00000101', 'RCP-2026-00000102'] as $no) {
            $r = FeeReceipt::create([
                'receipt_no' => $no,
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
            $r->items()->create([
                'fee_head_id' => $head->id,
                'description' => $head->name,
                'period' => 'Baisakh',
                'amount' => 500,
            ]);
        }

        // Must fail and write nothing (there are 2 active lines for one identity).
        $this->artisan('fees:backfill-bill-identities')->assertFailed();
        $this->assertSame(0, FeeBillIdentity::count());

        // Financial records untouched.
        $this->assertSame(2, FeeReceipt::where('student_id', $student->id)->count());
    }

    public function test_deleting_an_identity_row_does_not_touch_financial_history(): void
    {
        $student = $this->student('ADM-FK');
        $head = $this->feeHead('Tuition');

        $this->actingAs($this->accountant())
            ->post(route('admin.fees.store'), $this->storePayload($student, [$this->item($head)]))
            ->assertSessionHas('success');

        $identity = FeeBillIdentity::firstOrFail();
        $receipt = $identity->receipt;
        $itemsBefore = $receipt->items()->count();
        $paymentsBefore = $receipt->payments()->count();

        $identity->delete();

        // Deleting a claim never cascades into receipts, items or payments.
        $this->assertSame(1, FeeReceipt::where('id', $receipt->id)->count());
        $this->assertSame($itemsBefore, $receipt->items()->count());
        $this->assertSame($paymentsBefore, $receipt->payments()->count());
    }
}
