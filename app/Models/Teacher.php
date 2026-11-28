<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Teacher extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'first_name',
        'middle_name',
        'last_name',
        'gender',
        'phone',
        'email',
        'address',
        'qualification',
        'specialization',
        'joining_date',
        'profile_image',
        'bio',
        'is_visible',
        'status',
    ];

    protected $casts = [
        'joining_date' => 'date:Y-m-d',
        'is_visible' => 'boolean',
    ];

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true)->where('status', 'active');
    }

    /**
     * Resolve the public URL for the teacher's profile image stored on Cloudflare R2.
     */
    public function profileImageUrl(): string
    {
        $path = trim((string) $this->profile_image);
        if ($path === '') {
            return '';
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }

        return Storage::disk('r2')->url($path);
    }

    /**
     * Accessor for Full Name
     */
    public function getFullNameAttribute(): string
    {
        return collect([$this->first_name, $this->middle_name, $this->last_name])
            ->filter(fn ($part) => filled($part))
            ->implode(' ');
    }
}
