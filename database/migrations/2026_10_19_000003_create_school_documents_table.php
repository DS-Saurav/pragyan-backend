<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * New table, so an existing `documents` table (and its data) is left untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_documents', function (Blueprint $t) {
            $t->id();
            $t->string('title', 150);
            $t->text('description')->nullable();
            $t->string('category', 20)->default('general');
            $t->string('audience', 10)->default('all'); // all | students | parents | teachers | staff

            // Either an uploaded file (kept on private storage) or an external link, or both.
            $t->string('file_path')->nullable();
            $t->string('file_name')->nullable();
            $t->unsignedBigInteger('file_size')->nullable();
            $t->string('mime', 100)->nullable();
            $t->string('external_url', 500)->nullable();

            $t->unsignedSmallInteger('version')->default(1);
            $t->date('effective_date')->nullable(); // not shown to the audience before this date
            $t->date('expires_on')->nullable();     // hidden from the audience after this date
            $t->boolean('is_published')->default(true);

            $t->unsignedInteger('download_count')->default(0);
            $t->timestamp('last_downloaded_at')->nullable();

            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->softDeletes();
            $t->timestamps();

            $t->index(['audience', 'is_published']);
            $t->index('expires_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_documents');
    }
};
