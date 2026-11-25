<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class FeeReceipt extends Model
{
    use SoftDeletes;

    public const MODES = Money::MODES;

    public const STATUSES = ['paid', 'partial', 'unpaid', 'refunded'];

    protected $fillable = [
        'receipt_no', 'student_id', 'academic_year', 'receipt_date',
        'subtotal', 'discount', 'fine', 'total_due', 'paid_amount', 'balance',
        'status', 'remarks', 'collected_by', 'refunded_at', 'refund_reason',
    ];

    protected $casts = [
        'receipt_date' => 'date',
        'subtotal' => 'float',
        'discount' => 'float',
        'fine' => 'float',
        'total_due' => 'float',
        'paid_amount' => 'float',
        'balance' => 'float',
        'refunded_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // A soft-deleted receipt is no longer an active bill, so release its
        // billing-identity claims however the deletion is triggered. Financial
        // history (items, payments) is left untouched.
        static::deleting(function (FeeReceipt $receipt) {
            if (! $receipt->isForceDeleting()) {
                \Illuminate\Support\Facades\DB::table('fee_bill_identities')->where('fee_receipt_id', $receipt->id)->delete();
            }
        });
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(FeeReceiptItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(FeePayment::class);
    }

    public function billIdentities(): HasMany
    {
        return $this->hasMany(FeeBillIdentity::class);
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    public static function amountInWords(float $amount): string
    {
        return Money::words($amount);
    }

    /** RCP-2026-00000001 : the number restarts each academic year. Must run inside a transaction. */
    public static function nextNumber(string $academicYear): string
    {
        $prefix = 'RCP-'.substr($academicYear, 0, 4).'-';

        $last = static::withTrashed()
            ->where('receipt_no', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('receipt_no')
            ->value('receipt_no');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 8, '0', STR_PAD_LEFT);
    }

    /**
     * Create a receipt with the next number, retrying on a receipt-number
     * unique-constraint collision. The number lookup and insert run in one
     * transaction, and the whole transaction is retried so each attempt
     * regenerates a fresh candidate instead of reusing a colliding number.
     *
     * $afterCreate runs inside the same transaction (e.g. creating line items
     * or the initial payment) so it rolls back together with the receipt.
     *
     * @param  (callable(FeeReceipt): void)|null  $afterCreate
     *
     * @throws QueryException when retries are exhausted or a non-number error occurs
     */
    public static function createWithUniqueNumber(array $attributes, ?callable $afterCreate = null): self
    {
        $attempts = 3;

        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(function () use ($attributes, $afterCreate) {
                    $receipt = static::create($attributes + [
                        'receipt_no' => static::nextNumber($attributes['academic_year']),
                    ]);

                    if ($afterCreate) {
                        $afterCreate($receipt);
                    }

                    return $receipt;
                });
            } catch (QueryException $e) {
                // A billing-identity collision is a real duplicate bill: retrying
                // would never succeed, so re-throw it immediately and let the
                // caller report it. Only receipt-number collisions are retried.
                if ($attempt >= $attempts
                    || static::isBillIdentityCollision($e)
                    || ! static::isReceiptNumberCollision($e)) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Claim the billing identities for a receipt's identified line items.
     *
     * Only fully identified lines (non-null fee_head_id AND period) receive a
     * claim; partial lines are left unconstrained, matching the existing
     * duplicate rule. The first claim for an identity wins; a concurrent or
     * duplicate claim throws a unique violation that aborts the caller's
     * transaction.
     *
     * @param  iterable<int, \App\Models\FeeReceiptItem>  $items
     */
    public function claimIdentities(iterable $items): void
    {
        $now = now();

        $claims = [];

        foreach ($items as $item) {
            if (! $item->fee_head_id || ! $item->period) {
                continue;
            }

            $claims[] = [
                'student_id' => $this->student_id,
                'academic_year' => $this->academic_year,
                'fee_head_id' => $item->fee_head_id,
                'period' => $item->period,
                'fee_receipt_id' => $this->id,
                'fee_receipt_item_id' => $item->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($claims) {
            FeeBillIdentity::insert($claims);
        }
    }

    /** Release every billing-identity claim held by this receipt. */
    public function releaseIdentities(): void
    {
        DB::table('fee_bill_identities')->where('fee_receipt_id', $this->id)->delete();
    }

    /** True only for a duplicate billing-identity (student, year, head, period) violation. */
    public static function isBillIdentityCollision(QueryException $e): bool
    {
        // SQLite: "UNIQUE constraint failed: fee_bill_identities.student_id, ..."
        // MySQL/MariaDB: "Duplicate entry '...' for key 'fee_bill_identities.fee_bill_identity_unique'"
        // PostgreSQL: "duplicate key value violates unique constraint \"fee_bill_identity_unique\""
        // All name the identity table; none name fee_receipts, so this cannot
        // be confused with a receipt-number collision.
        return str_contains($e->getMessage(), 'fee_bill_identities')
            || str_contains($e->getMessage(), 'fee_bill_identity_unique');
    }

    /** True only for a duplicate receipt_no unique-constraint violation. */
    protected static function isReceiptNumberCollision(QueryException $e): bool
    {
        $message = $e->getMessage();

        // SQLite: "UNIQUE constraint failed: fee_receipts.receipt_no"
        if (str_contains($message, 'UNIQUE constraint failed: fee_receipts.receipt_no')) {
            return true;
        }

        // MySQL/MariaDB: "Duplicate entry '...' for key '...receipt_no...'"
        if (str_contains($message, 'Duplicate entry') && str_contains($message, 'receipt_no')) {
            return true;
        }

        // PostgreSQL: "duplicate key value violates unique constraint ..."
        if (str_contains($message, 'duplicate key value violates unique constraint')
            && str_contains($message, 'receipt_no')) {
            return true;
        }

        return false;
    }

    /** Recalculate paid amount, balance and status from the payments. */
    public function refreshTotals(): void
    {
        $paid = round((float) $this->payments()->sum('amount'), 2);
        $balance = max(round($this->total_due - $paid, 2), 0);

        $this->update([
            'paid_amount' => $paid,
            'balance' => $balance,
            'status' => $balance <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid'),
        ]);
    }

    /** True if this student already has a live (not refunded, not deleted) bill for the same fee head and period. */
    public static function alreadyBilled(int $studentId, string $academicYear, int $headId, string $period): bool
    {
        return FeeReceiptItem::where('fee_head_id', $headId)
            ->where('period', $period)
            ->whereHas('receipt', fn ($q) => $q
                ->where('student_id', $studentId)
                ->where('academic_year', $academicYear)
                ->where('status', '!=', 'refunded'))
            ->exists();
    }
}
