<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_photo_slots', function (Blueprint $table): void {
            $table->id();
            $table->string('slot_key', 40)->unique();
            $table->foreignId('media_asset_id')->nullable()->constrained('media_assets')->restrictOnDelete();
            $table->string('alt_text', 255)->nullable();
            $table->string('alt_text_ne', 255)->nullable();
            $table->string('caption', 500)->nullable();
            $table->string('caption_ne', 500)->nullable();
            $table->decimal('focal_x', 5, 2)->default(50);
            $table->decimal('focal_y', 5, 2)->default(50);
            // Staff-only provenance. Never include this field in public projections.
            $table->text('source_rights_note')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('public_photo_slot_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('public_photo_slot_id')->constrained('public_photo_slots')->cascadeOnDelete();
            $table->unsignedBigInteger('previous_media_asset_id')->nullable();
            $table->unsignedBigInteger('media_asset_id')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 20);
            $table->text('source_rights_note')->nullable();
            $table->timestamp('created_at');
        });

        $now = now();
        DB::table('public_photo_slots')->insert(array_map(
            static fn (string $key): array => ['slot_key' => $key, 'created_at' => $now, 'updated_at' => $now],
            ['home_hero', 'home_learning', 'admissions_hero', 'contact_visit'],
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('public_photo_slot_events');
        Schema::dropIfExists('public_photo_slots');
    }
};
