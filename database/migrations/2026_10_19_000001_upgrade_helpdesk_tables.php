<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the helpdesk tables, or upgrades them if a basic version already exists.
 * If your current table has a different name, change $table below.
 */
return new class extends Migration
{
    private string $table = 'helpdesk_tickets';

    public function up(): void
    {
        if (! Schema::hasTable($this->table)) {
            Schema::create($this->table, function (Blueprint $t) {
                $t->id();
                $t->string('ticket_no', 20)->unique();
                $t->string('subject', 150);
                $t->text('description')->nullable();
                $t->string('category', 30)->default('other');
                $t->string('priority', 10)->default('medium');
                $t->string('status', 15)->default('open');
                $t->foreignId('raised_by')->constrained('users')->cascadeOnDelete();
                $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $t->string('attachment_path')->nullable();
                $t->timestamp('last_activity_at')->nullable();
                $t->timestamp('resolved_at')->nullable();
                $t->timestamp('closed_at')->nullable();
                $t->softDeletes();
                $t->timestamps();
                $t->index(['status', 'priority']);
            });
        } else {
            Schema::table($this->table, function (Blueprint $t) {
                if (! Schema::hasColumn($this->table, 'ticket_no')) {
                    $t->string('ticket_no', 20)->nullable()->unique();
                }
                if (! Schema::hasColumn($this->table, 'description')) {
                    $t->text('description')->nullable();
                }
                if (! Schema::hasColumn($this->table, 'attachment_path')) {
                    $t->string('attachment_path')->nullable();
                }
                if (! Schema::hasColumn($this->table, 'last_activity_at')) {
                    $t->timestamp('last_activity_at')->nullable();
                }
                if (! Schema::hasColumn($this->table, 'resolved_at')) {
                    $t->timestamp('resolved_at')->nullable();
                }
                if (! Schema::hasColumn($this->table, 'closed_at')) {
                    $t->timestamp('closed_at')->nullable();
                }
                if (! Schema::hasColumn($this->table, 'deleted_at')) {
                    $t->softDeletes();
                }
            });
        }

        if (! Schema::hasTable('helpdesk_replies')) {
            Schema::create('helpdesk_replies', function (Blueprint $t) {
                $t->id();
                $t->foreignId('helpdesk_ticket_id')->constrained($this->table)->cascadeOnDelete();
                $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $t->string('type', 10)->default('reply'); // reply | system
                $t->text('body');
                $t->boolean('is_internal')->default(false); // staff-only note
                $t->string('attachment_path')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('helpdesk_replies');
    }
};
