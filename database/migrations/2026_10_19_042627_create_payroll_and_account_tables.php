<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->nullable()->unique()->constrained('teachers')->nullOnDelete();
            $table->string('name', 120);
            $table->string('designation', 100)->nullable();
            $table->string('type', 15)->default('teaching'); // teaching | non_teaching
            $table->string('phone', 30)->nullable();
            $table->date('joined_on')->nullable();
            $table->decimal('basic_salary', 10, 2)->default(0);
            $table->string('bank_name', 80)->nullable();
            $table->string('bank_account', 40)->nullable();
            $table->string('pan_no', 30)->nullable();
            $table->string('status', 10)->default('active'); // active | inactive
            $table->timestamps();

            $table->index(['status', 'type']);
        });

        Schema::create('salary_components', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('type', 10); // earning | deduction
            $table->string('calc', 10)->default('fixed'); // fixed | percent (of basic)
            $table->decimal('default_value', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('staff_salary_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_member_id')->constrained('staff_members')->cascadeOnDelete();
            $table->foreignId('salary_component_id')->constrained('salary_components')->cascadeOnDelete();
            $table->decimal('value', 10, 2); // amount, or percent of basic
            $table->timestamps();

            $table->unique(['staff_member_id', 'salary_component_id'], 'staff_component_unique');
        });

        Schema::create('salary_slips', function (Blueprint $table) {
            $table->id();
            $table->string('slip_no', 30)->unique();
            $table->foreignId('staff_member_id')->constrained('staff_members')->restrictOnDelete();
            $table->string('month', 7); // 2026-07
            $table->string('staff_name', 120);
            $table->string('designation', 100)->nullable();

            $table->decimal('basic', 10, 2);
            $table->unsignedTinyInteger('working_days')->default(26);
            $table->decimal('unpaid_days', 4, 1)->default(0);
            $table->decimal('gross', 10, 2);
            $table->decimal('total_deductions', 10, 2)->default(0);
            $table->decimal('net_pay', 10, 2);

            $table->string('status', 12)->default('generated'); // generated | paid
            $table->date('paid_on')->nullable();
            $table->string('payment_mode', 15)->nullable();
            $table->string('reference_no', 60)->nullable();
            $table->text('remarks')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['month', 'status']);
            $table->index('paid_on');
        });

        Schema::create('salary_slip_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_slip_id')->constrained('salary_slips')->cascadeOnDelete();
            $table->string('type', 10); // earning | deduction
            $table->string('name', 100);
            $table->decimal('amount', 10, 2);
            $table->boolean('is_system')->default(false); // auto lines such as unpaid leave
            $table->timestamps();
        });

        Schema::create('account_entries', function (Blueprint $table) {
            $table->id();
            $table->date('entry_date');
            $table->string('type', 10); // income | expense
            $table->string('category', 40);
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->string('mode', 15)->default('cash');
            $table->string('reference_no', 60)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['entry_date', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_entries');
        Schema::dropIfExists('salary_slip_lines');
        Schema::dropIfExists('salary_slips');
        Schema::dropIfExists('staff_salary_components');
        Schema::dropIfExists('salary_components');
        Schema::dropIfExists('staff_members');
    }
};
