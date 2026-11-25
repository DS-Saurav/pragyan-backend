<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_heads', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('frequency', 15)->default('monthly'); // monthly | termly | yearly | one_time
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('fee_structures', function (Blueprint $table) {
            $table->id();
            $table->string('academic_year', 20);
            $table->foreignId('school_class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignId('fee_head_id')->constrained('fee_heads')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->timestamps();

            $table->unique(['academic_year', 'school_class_id', 'fee_head_id'], 'fee_structure_unique');
        });

        Schema::create('fee_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_no', 30)->unique();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->string('academic_year', 20);
            $table->date('receipt_date');

            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('fine', 10, 2)->default(0);
            $table->decimal('total_due', 10, 2)->default(0);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->decimal('balance', 10, 2)->default(0);

            $table->string('status', 15)->default('unpaid'); // paid | partial | unpaid | refunded
            $table->text('remarks')->nullable();
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('refunded_at')->nullable();
            $table->string('refund_reason')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'receipt_date']);
            $table->index(['student_id', 'status']);
            $table->index('academic_year');
        });

        Schema::create('fee_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_receipt_id')->constrained('fee_receipts')->cascadeOnDelete();
            $table->foreignId('fee_head_id')->nullable()->constrained('fee_heads')->nullOnDelete();
            $table->string('description', 150);
            $table->string('period', 40)->nullable();
            $table->decimal('amount', 10, 2);
            $table->timestamps();
        });

        Schema::create('fee_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_receipt_id')->constrained('fee_receipts')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('mode', 15); // cash | bank | cheque | esewa | khalti | fonepay | other
            $table->string('reference_no', 60)->nullable();
            $table->date('paid_on');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('paid_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_payments');
        Schema::dropIfExists('fee_receipt_items');
        Schema::dropIfExists('fee_receipts');
        Schema::dropIfExists('fee_structures');
        Schema::dropIfExists('fee_heads');
    }
};
