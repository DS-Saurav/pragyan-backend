<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_asset_id')->index()->constrained('media_assets')->restrictOnDelete();
            $table->string('attachable_type', 60);
            $table->unsignedBigInteger('attachable_id');
            $table->string('placement', 60);
            $table->unsignedInteger('display_order')->default(0);
            $table->text('caption')->nullable();
            $table->string('alt_text')->nullable();
            $table->decimal('focal_x', 5, 2)->default(50);
            $table->decimal('focal_y', 5, 2)->default(50);
            $table->timestamps();
            $table->index(['attachable_type', 'attachable_id'], 'media_attachment_owner');
            // One image per placement slot; one asset can occupy multiple slots.
            $table->unique(['attachable_type', 'attachable_id', 'placement', 'display_order'], 'media_attachment_slot');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_attachments');
    }
};
