<?php

namespace Tests\Feature;

use App\Models\FeeReceipt;
use App\Models\Student;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FeeRefundTest extends TestCase
{
    use RefreshDatabase;

    private function accountant(): User
    {
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role' => 'accountant']);

        return $user;
    }

    private function student(string $adm = 'ADM-FEE-1'): Student
    {
        return Student::create([
            'first_name' => 'Fee',
            'last_name' => 'Payer',
            'gender' => 'male',
            'dob' => '2014-05-01',
            'class_applied' => 'Grade 6',
            'adm_number' => $adm,
            'status' => 'active',
        ]);
    }

    private function receipt(Student $student, float $totalDue, float $paid): FeeReceipt
    {
        $receipt = FeeReceipt::create([
            'receipt_no' => 'RCP-2026-'.str_pad((string) ((FeeReceipt::withTrashed()->max('id') ?? 0) + 1), 8, '0', STR_PAD_LEFT),
            'student_id' => $student->id,
            'academic_year' => '2026-2027',
            'receipt_date' => now()->toDateString(),
            'subtotal' => $totalDue,
            'discount' => 0,
            'fine' => 0,
            'total_due' => $totalDue,
            'paid_amount' => 0,
            'balance' => $totalDue,
            'status' => 'unpaid',
        ]);

        if ($paid > 0) {
            $receipt->payments()->create([
                'amount' => $paid,
                'mode' => 'cash',
                'paid_on' => now()->toDateString(),
                'received_by' => null,
            ]);
            $receipt->refreshTotals();
        }

        return $receipt->fresh();
    }

    private function refundPayload(): array
    {
        return ['reason' => 'Student transferred'];
    }

    public function test_valid_refund_marks_receipt_refunded_and_zeroes_balance(): void
    {
        $accountant = $this->accountant();
        $receipt = $this->receipt($this->student(), 1000, 400);

        $this->actingAs($accountant)
            ->post(route('admin.fees.refund', $receipt), $this->refundPayload())
            ->assertRedirect()
            ->assertSessionHas('success');

        $receipt->refresh();
        $this->assertSame('refunded', $receipt->status);
        $this->assertSame(0.0, (float) $receipt->balance);
        $this->assertNotNull($receipt->refunded_at);
        $this->assertSame('Student transferred', $receipt->refund_reason);
        // Accounting convention preserved: paid_amount is left intact.
        $this->assertSame(400.0, (float) $receipt->paid_amount);
    }

    public function test_second_refund_is_rejected_and_leaves_receipt_unchanged(): void
    {
        $accountant = $this->accountant();
        $receipt = $this->receipt($this->student(), 1000, 400);

        $this->actingAs($accountant)
            ->post(route('admin.fees.refund', $receipt), ['reason' => 'First'])
            ->assertSessionHas('success');

        $first = $receipt->fresh();

        $this->actingAs($accountant)
            ->post(route('admin.fees.refund', $receipt), ['reason' => 'Second'])
            ->assertSessionHasErrors('refund');

        $second = $receipt->fresh();
        $this->assertSame('refunded', $second->status);
        $this->assertSame('First', $second->refund_reason);
        $this->assertEquals($first->refunded_at, $second->refunded_at);
    }

    public function test_refund_of_receipt_with_no_payment_is_rejected_and_unchanged(): void
    {
        $accountant = $this->accountant();
        $receipt = $this->receipt($this->student(), 1000, 0);

        $this->actingAs($accountant)
            ->post(route('admin.fees.refund', $receipt), $this->refundPayload())
            ->assertSessionHasErrors('refund');

        $receipt->refresh();
        $this->assertSame('unpaid', $receipt->status);
        $this->assertNull($receipt->refunded_at);
        $this->assertNull($receipt->refund_reason);
    }

    public function test_refund_failure_rolls_back_all_writes(): void
    {
        $accountant = $this->accountant();
        $receipt = $this->receipt($this->student(), 1000, 400);

        FeeReceipt::updating(function () {
            throw new \RuntimeException('forced failure');
        });

        try {
            $this->actingAs($accountant)
                ->withoutExceptionHandling()
                ->post(route('admin.fees.refund', $receipt), $this->refundPayload());
            $this->fail('Expected the forced failure to propagate.');
        } catch (\RuntimeException $e) {
            $this->assertSame('forced failure', $e->getMessage());
        } finally {
            FeeReceipt::flushEventListeners();
        }

        $receipt->refresh();
        $this->assertSame('partial', $receipt->status);
        $this->assertNull($receipt->refunded_at);
        $this->assertNull($receipt->refund_reason);
        $this->assertSame(400.0, (float) $receipt->paid_amount);
    }

    public function test_payment_cannot_be_recorded_once_receipt_is_refunded(): void
    {
        $accountant = $this->accountant();
        $receipt = $this->receipt($this->student(), 1000, 400);

        $this->actingAs($accountant)
            ->post(route('admin.fees.refund', $receipt), $this->refundPayload())
            ->assertSessionHas('success');

        $paymentsBefore = $receipt->payments()->count();

        $this->actingAs($accountant)
            ->post(route('admin.fees.pay', $receipt), [
                'amount' => 100,
                'mode' => 'cash',
                'paid_on' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('payment');

        $receipt->refresh();
        $this->assertSame('refunded', $receipt->status);
        $this->assertSame($paymentsBefore, $receipt->payments()->count());
        $this->assertSame(400.0, (float) $receipt->paid_amount);
    }

    public function test_refund_requires_authorization(): void
    {
        $receipt = $this->receipt($this->student(), 1000, 400);

        // Authenticated staff without fees.refund.
        $teacher = User::factory()->create();
        UserRole::create(['user_id' => $teacher->id, 'role' => 'teacher']);

        $this->actingAs($teacher)
            ->post(route('admin.fees.refund', $receipt), $this->refundPayload())
            ->assertForbidden();

        // Unauthenticated requests are denied (guest POST to a gate-protected
        // route returns 403 rather than a login redirect).
        $this->post(route('admin.fees.refund', $receipt), $this->refundPayload())
            ->assertForbidden();

        $receipt->refresh();
        $this->assertSame('partial', $receipt->status);
        $this->assertNull($receipt->refunded_at);
        $this->assertTrue($teacher->hasAnyRole(['teacher']));
        $this->assertFalse($teacher->hasPermission('fees.refund'));
    }

    public function test_refund_runs_inside_a_transaction(): void
    {
        $accountant = $this->accountant();
        $receipt = $this->receipt($this->student(), 1000, 400);

        $baselineLevel = DB::transactionLevel();
        $updateWasInTransaction = null;
        FeeReceipt::updating(function () use (&$updateWasInTransaction, $baselineLevel) {
            $updateWasInTransaction = DB::transactionLevel() > $baselineLevel;
        });

        try {
            $this->actingAs($accountant)
                ->post(route('admin.fees.refund', $receipt), $this->refundPayload())
                ->assertSessionHas('success');
        } finally {
            FeeReceipt::flushEventListeners();
        }

        $this->assertTrue($updateWasInTransaction, 'Refund update should run inside a DB transaction.');
        $this->assertSame($baselineLevel, DB::transactionLevel(), 'The controller transaction should have committed.');
        $this->assertSame('refunded', $receipt->fresh()->status);
    }
}
