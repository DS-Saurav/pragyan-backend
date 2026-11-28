<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gallery_albums')) {
            return;
        }

        Schema::table('gallery_albums', function (Blueprint $table): void {
            if (Schema::hasColumn('gallery_albums', 'cover_image')) {
                $table->dropColumn('cover_image');
            }

            if (! Schema::hasColumn('gallery_albums', 'cover_image_id')) {
                $table->foreignId('cover_image_id')
                    ->nullable()
                    ->after('description')
                    ->constrained('gallery')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('gallery_albums')) {
            return;
        }

        Schema::table('gallery_albums', function (Blueprint $table): void {
            if (Schema::hasColumn('gallery_albums', 'cover_image_id')) {
                $table->dropForeign(['cover_image_id']);
                $table->dropColumn('cover_image_id');
            }

            if (! Schema::hasColumn('gallery_albums', 'cover_image')) {
                $table->string('cover_image', 500)->nullable()->after('description');
            }
        });
    }
};
