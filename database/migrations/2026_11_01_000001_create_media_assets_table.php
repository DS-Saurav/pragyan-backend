<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->string('disk', 40)->default('media_originals');
            $table->string('original_path', 160)->unique();
            $table->string('public_disk', 40)->default('media_derivatives');
            $table->string('public_path', 160)->nullable()->unique();
            $table->string('original_filename');
            $table->string('storage_filename', 64)->unique();
            $table->string('mime_type', 40);
            $table->unsignedBigInteger('file_size');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->char('checksum', 64)->index();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('default_alt_text')->nullable();
            $table->string('category', 60)->nullable()->index();
            // Preserve attribution when a login is deleted; originals are never cascaded.
            $table->foreignId('uploaded_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('published_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};
