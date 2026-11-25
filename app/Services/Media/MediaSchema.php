<?php

namespace App\Services\Media;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Schema;

class MediaSchema
{
    public function ready(): bool
    {
        $required = [
            'media_assets' => ['id', 'disk', 'original_path', 'public_disk', 'public_path', 'original_filename',
                'storage_filename', 'mime_type', 'file_size', 'width', 'height', 'checksum', 'title',
                'description', 'default_alt_text', 'category', 'uploaded_by', 'status', 'submitted_at',
                'reviewed_by', 'reviewed_at', 'published_by', 'published_at', 'archived_at',
                'photographer_credit', 'is_decorative', 'consent_state', 'consent_confirmed_by',
                'consent_confirmed_at', 'consent_withdrawn_by', 'consent_withdrawn_at', 'created_at', 'updated_at'],
            'media_attachments' => ['id', 'media_asset_id', 'attachable_type', 'attachable_id', 'placement',
                'display_order', 'caption', 'alt_text', 'focal_x', 'focal_y', 'created_at', 'updated_at'],
        ];
        foreach ($required as $table => $columns) {
            if (! Schema::hasTable($table) || array_diff($columns, Schema::getColumnListing($table))) {
                return false;
            }
        }

        return true;
    }

    public function ensureReady(): void
    {
        if (! $this->ready()) {
            throw new HttpResponseException(response()->json([
                'message' => 'The Media Library is not available yet. Please contact the school administrator.',
            ], 503));
        }
    }
}
