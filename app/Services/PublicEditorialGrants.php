<?php

namespace App\Services;

use App\Models\PublicEditorialGrant;
use App\Models\PublicEditorialGrantEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublicEditorialGrants
{
    /**
     * Replace one staff member's exact public editorial permissions. An empty
     * list revokes every direct grant, including for an ineligible recipient.
     *
     * @param  list<string>  $permissions
     */
    public function sync(User $actor, User $recipient, array $permissions): void
    {
        abort_unless($actor->status === 'active' && $actor->hasRole('admin'), 403);

        $requested = array_values(array_unique($permissions));
        if (count(array_diff($requested, PublicEditorialGrant::allowedPermissions())) > 0) {
            throw ValidationException::withMessages([
                'permissions' => 'Choose only the listed public editorial permissions.',
            ]);
        }

        DB::transaction(function () use ($actor, $recipient, $requested): void {
            $target = User::query()->lockForUpdate()->findOrFail($recipient->id);

            if ($requested !== [] && ! PublicEditorialGrant::isEligibleRecipient($target)) {
                throw ValidationException::withMessages([
                    'permissions' => 'Public editorial access is available only to active staff without an Admin, Parent or Student role.',
                ]);
            }

            $existing = PublicEditorialGrant::query()
                ->where('user_id', $target->id)
                ->lockForUpdate()
                ->pluck('permission')
                ->all();

            foreach (array_diff($existing, $requested) as $permission) {
                PublicEditorialGrant::query()
                    ->where('user_id', $target->id)
                    ->where('permission', $permission)
                    ->delete();
                $this->record($target, $actor, $permission, 'revoked');
            }

            foreach (array_diff($requested, $existing) as $permission) {
                PublicEditorialGrant::create([
                    'user_id' => $target->id,
                    'permission' => $permission,
                    'granted_by' => $actor->id,
                ]);
                $this->record($target, $actor, $permission, 'granted');
            }
        });
    }

    private function record(User $recipient, User $actor, string $permission, string $action): void
    {
        PublicEditorialGrantEvent::create([
            'target_user_id' => $recipient->id,
            'permission' => $permission,
            'action' => $action,
            'actor_id' => $actor->id,
        ]);
    }
}
