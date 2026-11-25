<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Billing identity: at most one active (student, year, fee head, period)
     * claim exists at a time. The unique constraint is the authoritative
     * concurrency guard for duplicate fee billing.
     *
     * This migration only creates the empty table and its constraint. It does
     * NOT backfill existing receipts; run `php artisan fees:backfill-bill-identities`
     * for that.
     *
     * Deletion direction is identity -> receipt/item (cascade), so removing a
     * receipt or item cleans up its claims. Nothing cascades FROM this table
     * into fee_receipts, fee_receipt_items or fee_payments: identity rows carry
     * no child records and hold no financial amounts, so deleting a claim can
     * never rewrite billing or payment history.
     */
    public function up(): void
    {
        Schema::create('fee_bill_identities', function (Blueprint $table) {
            $table->id();

            // restrictOnDelete matches fee_receipts: a student or fee head that
            // still has billing claims cannot be silently removed.
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->string('academic_year', 20);
            $table->foreignId('fee_head_id')->constrained('fee_heads')->restrictOnDelete();
            $table->string('period', 40);

            $table->foreignId('fee_receipt_id')->constrained('fee_receipts')->cascadeOnDelete();
            $table->foreignId('fee_receipt_item_id')->constrained('fee_receipt_items')->cascadeOnDelete()->unique();

            $table->timestamps();

            $table->unique(
                ['student_id', 'academic_year', 'fee_head_id', 'period'],
                'fee_bill_identity_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_bill_identities');
    }
};
