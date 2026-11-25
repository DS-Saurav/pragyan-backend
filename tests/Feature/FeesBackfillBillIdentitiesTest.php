<?php

namespace Tests\Feature;

use App\Models\FeeBillIdentity;
use App\Models\FeeHead;
use App\Models\FeeReceipt;
use App\Models\FeeReceiptItem;
use App\Models\Student;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class FeesBackfillBillIdentitiesTest extends TestCase
{
    use RefreshDatabase;

    private const YEAR = '2026-2027';

    private function accountant(): User
    {
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role' => 'accountant']);

        return $user;
    }

    private function student(string $adm): Student
    {
        return Student::create([
            'adm_number' => $adm,
            'first_name' => 'Test',
            'last_name' => 'Student',
            'gender' => 'male',
            'dob' => '2014-05-01',
            'class_applied' => 'Grade 6',
            'status' => 'active',
        ]);
    }

    private function makeHead(string $name): FeeHead
    {
        return FeeHead::create([
            'name' => $name,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);
    }

    private function makeActiveReceipt(Student $student, User $collectedBy): FeeReceipt
    {
        return FeeReceipt::create([
            'receipt_no' => 'RCP-BACK-'.uniqid(),
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
            'collected_by' => $collectedBy->id,
        ]);
    }

    public function test_successful_backfill_inserts_correct_claims(): void
    {
        $accountant = $this->accountant();
        $student = $this->student('ADM-B1');
        $head = $this->makeHead('Tuition');
        $receipt = $this->makeActiveReceipt($student, $accountant);

        $item = FeeReceiptItem::create([
            'fee_receipt_id' => $receipt->id,
            'fee_head_id' => $head->id,
            'description' => 'Tuition',
            'period' => 'Baisakh',
            'amount' => 500,
        ]);

        $this->artisan('fees:backfill-bill-identities')->assertExitCode(0);

        $this->assertSame(1, FeeBillIdentity::count());
        $this->assertDatabaseHas('fee_bill_identities', [
            'student_id' => $student->id,
            'academic_year' => self::YEAR,
            'fee_head_id' => $head->id,
            'period' => 'Baisakh',
            'fee_receipt_id' => $receipt->id,
            'fee_receipt_item_id' => $item->id,
        ]);
    }

    public function test_dry_run_reports_without_persisting_claims(): void
    {
        $accountant = $this->accountant();
        $student = $this->student('ADM-DR1');
        $head = $this->makeHead('Tuition');
        $receipt = $this->makeActiveReceipt($student, $accountant);

        FeeReceiptItem::create([
            'fee_receipt_id' => $receipt->id,
            'fee_head_id' => $head->id,
            'description' => 'Tuition',
            'period' => 'Baisakh',
            'amount' => 500,
        ]);

        $this->assertSame(0, FeeBillIdentity::count());
        $this->artisan('fees:backfill-bill-identities', ['--dry-run' => true])->assertExitCode(0);
        $this->assertSame(0, FeeBillIdentity::count());
    }

    public function test_duplicate_identities_report_and_exit_without_writing(): void
    {
        $accountant = $this->accountant();
        $student = $this->student('ADM-D1');
        $head = $this->makeHead('Tuition');
        $r1 = $this->makeActiveReceipt($student, $accountant);
        $r2 = $this->makeActiveReceipt($student, $accountant);

        FeeReceiptItem::create([
            'fee_receipt_id' => $r1->id,
            'fee_head_id' => $head->id,
            'description' => 'Tuition',
            'period' => 'Baisakh',
            'amount' => 500,
        ]);
        FeeReceiptItem::create([
            'fee_receipt_id' => $r2->id,
            'fee_head_id' => $head->id,
            'description' => 'Tuition',
            'period' => 'Baisakh',
            'amount' => 500,
        ]);

        $this->artisan('fees:backfill-bill-identities')->assertExitCode(1);
        $this->assertSame(0, FeeBillIdentity::count());
    }

    public function test_excludes_refunded_soft_deleted_and_partial_items(): void
    {
        $accountant = $this->accountant();
        $student = $this->student('ADM-E1');
        $head = $this->makeHead('Tuition');

        $rRefunded = $this->makeActiveReceipt($student, $accountant);
        $rRefunded->update(['status' => 'refunded']);
        FeeReceiptItem::create([
            'fee_receipt_id' => $rRefunded->id,
            'fee_head_id' => $head->id,
            'description' => 'Tuition',
            'period' => 'Baisakh',
            'amount' => 500,
        ]);

        $rDeleted = $this->makeActiveReceipt($student, $accountant);
        $rDeleted->delete();
        FeeReceiptItem::create([
            'fee_receipt_id' => $rDeleted->id,
            'fee_head_id' => $head->id,
            'description' => 'Tuition',
            'period' => 'Baisakh',
            'amount' => 500,
        ]);

        $rPartial = $this->makeActiveReceipt($student, $accountant);
        FeeReceiptItem::create([
            'fee_receipt_id' => $rPartial->id,
            'fee_head_id' => null,
            'description' => 'Other',
            'period' => 'Baisakh',
            'amount' => 100,
        ]);
        FeeReceiptItem::create([
            'fee_receipt_id' => $rPartial->id,
            'fee_head_id' => $head->id,
            'description' => 'Tuition',
            'period' => null,
            'amount' => 400,
        ]);

        $this->artisan('fees:backfill-bill-identities')->assertExitCode(0);
        $this->assertSame(0, FeeBillIdentity::count());
    }

    public function test_idempotent_rerun_skips_existing_claims(): void
    {
        $accountant = $this->accountant();
        $student = $this->student('ADM-I1');
        $head = $this->makeHead('Tuition');
        $receipt = $this->makeActiveReceipt($student, $accountant);

        FeeReceiptItem::create([
            'fee_receipt_id' => $receipt->id,
            'fee_head_id' => $head->id,
            'description' => 'Tuition',
            'period' => 'Baisakh',
            'amount' => 500,
        ]);

        $this->artisan('fees:backfill-bill-identities')->assertExitCode(0);
        $this->assertSame(1, FeeBillIdentity::count());

        $this->artisan('fees:backfill-bill-identities')->assertExitCode(0);
        $this->assertSame(1, FeeBillIdentity::count());
    }
}
