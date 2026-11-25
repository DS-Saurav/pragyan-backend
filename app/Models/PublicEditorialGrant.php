<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicEditorialGrant extends Model
{
    /**
     * Only public editorial actions belong here. In particular, internal
     * calendar, applicant, staff, finance and school settings permissions do not.
     *
     * @var array<string, array<string, string>>
     */
    public const GROUPS = [
        'Media Library' => [
            'media.view' => 'View editorial media',
            'media.upload' => 'Upload and prepare images',
            'media.review' => 'Review images and consent',
            'media.publish' => 'Publish or withdraw images',
        ],
        'News' => [
            'news.author' => 'Draft stories',
            'news.review' => 'Review stories',
            'news.publish' => 'Publish or withdraw stories',
        ],
        'Gallery' => [
            'gallery.author' => 'Draft albums',
            'gallery.review' => 'Review albums',
            'gallery.publish' => 'Publish or withdraw albums',
        ],
        'Notices' => [
            'notices.author' => 'Draft public notices',
            'notices.review' => 'Review public notices',
            'notices.publish' => 'Publish or withdraw public notices',
        ],
        'About and Academics' => [
            'public_pages.author' => 'Draft public pages',
            'public_pages.review' => 'Review public pages',
            'public_pages.publish' => 'Publish or withdraw public pages',
        ],
        'Public Team' => [
            'public_team.author' => 'Draft public profiles',
            'public_team.review' => 'Review public profiles',
            'public_team.publish' => 'Publish or withdraw public profiles',
        ],
    ];

    protected $fillable = ['user_id', 'permission', 'granted_by'];

    /** @return list<string> */
    public static function allowedPermissions(): array
    {
        return array_keys(array_merge(...array_values(self::GROUPS)));
    }

    public static function allows(string $permission): bool
    {
        return in_array($permission, self::allowedPermissions(), true);
    }

    public static function isEligibleRecipient(User $user): bool
    {
        $roles = $user->roleNames();
        $staffRoles = array_diff(array_keys((array) config('permissions.role_labels', [])), ['admin', 'parent', 'student']);

        return $user->status === 'active'
            && ! array_intersect($roles, ['admin', 'parent', 'student'])
            && (bool) array_intersect($roles, $staffRoles);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
