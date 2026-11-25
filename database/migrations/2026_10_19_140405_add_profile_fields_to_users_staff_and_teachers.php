<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 30)->nullable();
            }
            if (! Schema::hasColumn('users', 'gender')) {
                $table->string('gender', 10)->nullable();
            }
            if (! Schema::hasColumn('users', 'status')) {
                $table->string('status', 12)->default('active'); // active | suspended
            }
            if (! Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable();
            }
            if (! Schema::hasColumn('users', 'photo')) {
                $table->string('photo')->nullable();
            }
        });

        if (Schema::hasTable('staff_members')) {
            Schema::table('staff_members', function (Blueprint $table) {
                if (! Schema::hasColumn('staff_members', 'user_id')) {
                    $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
                    $table->string('email', 150)->nullable();
                    $table->string('gender', 10)->nullable();
                    $table->string('qualification', 150)->nullable();
                    $table->text('bio')->nullable();
                    $table->string('photo')->nullable();
                    $table->boolean('show_on_website')->default(false);
                    $table->string('team_group', 15)->default('teaching'); // leadership | teaching | support
                    $table->unsignedSmallInteger('display_order')->default(0);

                    $table->index(['show_on_website', 'team_group', 'display_order'], 'staff_website_idx');
                }
            });
        }

        if (Schema::hasTable('teachers') && ! Schema::hasColumn('teachers', 'user_id')) {
            Schema::table('teachers', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('teachers') && Schema::hasColumn('teachers', 'user_id')) {
            Schema::table('teachers', fn (Blueprint $t) => $t->dropConstrainedForeignId('user_id'));
        }

        if (Schema::hasTable('staff_members') && Schema::hasColumn('staff_members', 'user_id')) {
            Schema::table('staff_members', function (Blueprint $t) {
                $t->dropConstrainedForeignId('user_id');
                $t->dropColumn(['email', 'gender', 'qualification', 'bio', 'photo', 'show_on_website', 'team_group', 'display_order']);
            });
        }

        Schema::table('users', function (Blueprint $t) {
            foreach (['phone', 'gender', 'status', 'last_login_at', 'photo'] as $c) {
                if (Schema::hasColumn('users', $c)) {
                    $t->dropColumn($c);
                }
            }
        });
    }
};
