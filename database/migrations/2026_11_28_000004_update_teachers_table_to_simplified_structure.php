<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('teachers')) {
            Schema::create('teachers', function (Blueprint $table): void {
                $table->id();
                $table->string('slug', 180)->unique();
                $table->string('first_name', 100);
                $table->string('middle_name', 100)->nullable();
                $table->string('last_name', 100);
                $table->string('gender', 20)->nullable();
                $table->string('phone', 20)->nullable();
                $table->string('email', 150)->nullable();
                $table->text('address')->nullable();
                $table->string('qualification', 150)->nullable();
                $table->string('specialization', 150)->nullable();
                $table->date('joining_date')->nullable();
                $table->string('profile_image', 255)->nullable();
                $table->text('bio')->nullable();
                $table->boolean('is_visible')->default(true);
                $table->string('status', 20)->default('active');
                $table->timestamps();

                $table->index(['is_visible', 'status'], 'teachers_visible_status_idx');
            });

            return;
        }

        $indexes = collect(Schema::getIndexes('teachers'))->pluck('name')->all();

        Schema::table('teachers', function (Blueprint $table) use ($indexes): void {
            if (Schema::hasColumn('teachers', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }

            if (in_array('teachers_username_unique', $indexes, true)) {
                $table->dropUnique('teachers_username_unique');
            }

            if (in_array('teachers_email_unique', $indexes, true)) {
                $table->dropUnique('teachers_email_unique');
            }

            $dropCols = array_values(array_filter(
                ['department', 'class_teacher_of', 'username', 'assignments'],
                fn (string $col): bool => Schema::hasColumn('teachers', $col)
            ));
            if ($dropCols !== []) {
                $table->dropColumn($dropCols);
            }
        });

        Schema::table('teachers', function (Blueprint $table): void {
            $table->string('first_name', 100)->change();
            $table->string('last_name', 100)->change();
            $table->string('phone', 20)->nullable()->change();
            $table->string('email', 150)->nullable()->change();
            if (Schema::hasColumn('teachers', 'specialization')) {
                $table->string('specialization', 150)->nullable()->change();
            } else {
                $table->string('specialization', 150)->nullable()->after('qualification');
            }
            $table->string('status', 20)->default('active')->change();

            if (! Schema::hasColumn('teachers', 'slug')) {
                $table->string('slug', 180)->nullable()->after('id');
            }
            if (! Schema::hasColumn('teachers', 'middle_name')) {
                $table->string('middle_name', 100)->nullable()->after('first_name');
            }
            if (! Schema::hasColumn('teachers', 'gender')) {
                $table->string('gender', 20)->nullable()->after('last_name');
            }
            if (! Schema::hasColumn('teachers', 'address')) {
                $table->text('address')->nullable()->after('email');
            }
            if (! Schema::hasColumn('teachers', 'qualification')) {
                $table->string('qualification', 150)->nullable()->after('address');
            }
            if (! Schema::hasColumn('teachers', 'joining_date')) {
                $table->date('joining_date')->nullable()->after('specialization');
            }
            if (! Schema::hasColumn('teachers', 'profile_image')) {
                $table->string('profile_image', 255)->nullable()->after('joining_date');
            }
            if (! Schema::hasColumn('teachers', 'bio')) {
                $table->text('bio')->nullable()->after('profile_image');
            }
            if (! Schema::hasColumn('teachers', 'is_visible')) {
                $table->boolean('is_visible')->default(true)->after('bio');
            }
        });

        // Backfill slugs for any existing teacher records before enforcing NOT NULL + UNIQUE.
        DB::table('teachers')
            ->whereNull('slug')
            ->orWhere('slug', '')
            ->orderBy('id')
            ->get(['id', 'first_name', 'last_name'])
            ->each(function (object $row): void {
                $base = Str::slug(trim(($row->first_name ?? '').' '.($row->last_name ?? '')));
                $slug = ($base !== '' ? $base : 'teacher').'-'.$row->id;
                DB::table('teachers')->where('id', $row->id)->update(['slug' => Str::limit($slug, 180, '')]);
            });

        $updatedIndexes = collect(Schema::getIndexes('teachers'))->pluck('name')->all();

        Schema::table('teachers', function (Blueprint $table) use ($updatedIndexes): void {
            $table->string('slug', 180)->nullable(false)->change();

            if (! in_array('teachers_slug_unique', $updatedIndexes, true)) {
                $table->unique('slug', 'teachers_slug_unique');
            }

            if (! in_array('teachers_visible_status_idx', $updatedIndexes, true)) {
                $table->index(['is_visible', 'status'], 'teachers_visible_status_idx');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('teachers')) {
            return;
        }

        $indexes = collect(Schema::getIndexes('teachers'))->pluck('name')->all();

        Schema::table('teachers', function (Blueprint $table) use ($indexes): void {
            if (in_array('teachers_visible_status_idx', $indexes, true)) {
                $table->dropIndex('teachers_visible_status_idx');
            }
            if (in_array('teachers_slug_unique', $indexes, true)) {
                $table->dropUnique('teachers_slug_unique');
            }

            $dropCols = array_values(array_filter(
                ['slug', 'middle_name', 'gender', 'address', 'qualification', 'joining_date', 'profile_image', 'bio', 'is_visible'],
                fn (string $col): bool => Schema::hasColumn('teachers', $col)
            ));
            if ($dropCols !== []) {
                $table->dropColumn($dropCols);
            }

            if (! Schema::hasColumn('teachers', 'department')) {
                $table->string('department')->default('General');
            }
            if (! Schema::hasColumn('teachers', 'class_teacher_of')) {
                $table->string('class_teacher_of')->nullable();
            }
            if (! Schema::hasColumn('teachers', 'username')) {
                $table->string('username')->nullable()->unique();
            }
            if (! Schema::hasColumn('teachers', 'assignments')) {
                $table->json('assignments')->nullable();
            }
            if (! Schema::hasColumn('teachers', 'user_id')) {
                $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            }
        });
    }
};
