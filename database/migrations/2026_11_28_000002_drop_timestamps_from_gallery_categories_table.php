<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gallery_categories')) {
            return;
        }

        $columns = array_values(array_filter(
            ['created_at', 'updated_at'],
            fn (string $column): bool => Schema::hasColumn('gallery_categories', $column),
        ));

        if ($columns !== []) {
            Schema::table('gallery_categories', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('gallery_categories') && ! Schema::hasColumn('gallery_categories', 'created_at')) {
            Schema::table('gallery_categories', function (Blueprint $table): void {
                $table->timestamps();
            });
        }
    }
};
