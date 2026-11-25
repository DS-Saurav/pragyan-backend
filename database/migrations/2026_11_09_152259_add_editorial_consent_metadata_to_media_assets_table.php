<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('media_assets', function (Blueprint $table) {
            // Consent evidence stays private to the editorial workflow; only its
            // resulting availability is ever used by the public derivative route.
            $table->string('photographer_credit')->nullable()->after('description');
            $table->boolean('is_decorative')->default(false)->after('default_alt_text');
            $table->string('consent_state', 20)->default('not_required')->index()->after('is_decorative');
            $table->foreignId('consent_confirmed_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamp('consent_confirmed_at')->nullable();
            $table->foreignId('consent_withdrawn_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamp('consent_withdrawn_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media_assets', function (Blueprint $table) {
            // SQLite rebuilds the table for a dropped column. Remove the
            // explicit indexes before those rebuilds so no orphaned index is
            // carried over to the next column drop.
            $table->dropForeign(['consent_confirmed_by']);
            $table->dropIndex(['consent_confirmed_by']);
            $table->dropColumn('consent_confirmed_by');
            $table->dropForeign(['consent_withdrawn_by']);
            $table->dropIndex(['consent_withdrawn_by']);
            $table->dropColumn('consent_withdrawn_by');
            $table->dropIndex(['consent_state']);
            $table->dropColumn([
                'photographer_credit',
                'is_decorative',
                'consent_state',
                'consent_confirmed_at',
                'consent_withdrawn_at',
            ]);
        });
    }
};
