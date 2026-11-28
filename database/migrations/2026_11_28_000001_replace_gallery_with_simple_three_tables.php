<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 120)->unique();
            $table->boolean('is_active')->default(true)->index();
        });

        Schema::create('gallery_albums', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')
                ->nullable()
                ->constrained('gallery_categories')
                ->nullOnDelete();
            $table->string('title', 255);
            $table->string('slug', 255)->unique();
            $table->text('description')->nullable();
            $table->string('cover_image', 500)->nullable();
            $table->date('event_date')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['is_active', 'created_at'], 'gallery_albums_active_created_idx');
        });

        Schema::create('gallery', function (Blueprint $table) {
            $table->id();
            $table->foreignId('album_id')
                ->constrained('gallery_albums')
                ->cascadeOnDelete();
            $table->string('alt', 255)->default('');
            $table->string('caption', 500)->nullable();
            $table->string('image_path', 500);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['album_id', 'sort_order'], 'gallery_album_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery');
        Schema::dropIfExists('gallery_albums');
        Schema::dropIfExists('gallery_categories');
    }
};
