<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PublicEditorialGrant;
use App\Models\PublicEditorialGrantEvent;
use App\Models\StaffMember;
use App\Models\Teacher;
use App\Models\User;
use App\Models\UserRole;
use App\Services\PublicEditorialGrants;
use App\Support\Photo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserController extends Controller
{
    private const GENDERS = ['male' => 'Male', 'female' => 'Female', 'other' => 'Other'];

    private function filtered(Request $request)
    {
        return User::with(['userRoles', 'staff'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->search;
                $q->where(fn ($q) => $q->where('name', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%")
                    ->orWhere('phone', 'like', "%{$s}%"));
            })
            ->when($request->role === 'none', fn ($q) => $q->whereDoesntHave('userRoles'))
            ->when($request->filled('role') && $request->role !== 'none',
                fn ($q) => $q->whereHas('userRoles', fn ($r) => $r->where('role', $request->role)))
            ->when($request->filled('gender') && isset(self::GENDERS[$request->gender]), fn ($q) => $q->where('gender', $request->gender))
            ->when($request->staff === 'staff', fn ($q) => $q->whereHas('staff'))
            ->when($request->staff === 'other', fn ($q) => $q->whereDoesntHave('staff'));
    }

    public function index(Request $request)
    {
        $tab = in_array($request->tab, ['active', 'suspended'], true) ? $request->tab : 'all';
        $perPage = in_array((int) $request->per_page, [10, 25, 50, 100], true) ? (int) $request->per_page : 10;

        $users = $this->filtered($request)
            ->when($tab !== 'all', fn ($q) => $q->where('status', $tab))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        $raw = $this->filtered($request)->setEagerLoads([])->reorder()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $counts = [
            'all' => (int) $raw->sum(),
            'active' => (int) ($raw['active'] ?? 0),
            'suspended' => (int) ($raw['suspended'] ?? 0),
        ];

        $stats = [
            'total' => User::count(),
            'active' => User::where('status', 'active')->count(),
            'suspended' => User::where('status', 'suspended')->count(),
            'website' => StaffMember::website()->count(),
            'no_role' => User::whereDoesntHave('userRoles')->count(),
            'staff_no_login' => StaffMember::where('status', 'active')->whereNull('user_id')->count(),
        ];

        $teachersJs = Teacher::orderBy('first_name')->get()->map(fn ($t) => [
            'id' => $t->id,
            'user_id' => $t->user_id,
            'name' => trim(($t->first_name ?? '').' '.($t->last_name ?? '')),
            'email' => $t->email ?? '',
            'phone' => $t->phone ?? '',
        ])->values();

        $freeStaffJs = StaffMember::whereNull('user_id')->orderBy('name')->get()
            ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'designation' => $s->designation])->values();

        return view('admin.users.index', [
            'users' => $users,
            'counts' => $counts,
            'stats' => $stats,
            'tab' => $tab,
            'perPage' => $perPage,
            'genders' => self::GENDERS,
            'roleKeys' => array_keys(config('permissions.roles', [])),
            'teachersJs' => $teachersJs,
            'freeStaffJs' => $freeStaffJs,
            'teacherByUser' => Teacher::whereNotNull('user_id')->pluck('id', 'user_id'),
        ]);
    }

    public function store(Request $request)
    {
        [$user, $plain] = $this->save($request, null);

        $note = $request->boolean('is_staff')
            ? ' A staff profile was created: set the basic salary in Payroll → Staff & salary setup.'
            : '';

        return back()
            ->with('success', "Account created for {$user->name}.".$note)
            ->with('credentials', ['name' => $user->name, 'email' => $user->email, 'password' => $plain]);
    }

    public function update(Request $request, User $user)
    {
        $this->guardTarget($user);
        $this->save($request, $user);

        return back()->with('success', 'User updated.');
    }

    public function toggleStatus(User $user)
    {
        $this->guardTarget($user);

        if ($user->id === auth()->id()) {
            return back()->withErrors(['user' => 'You cannot suspend your own account.']);
        }

        if ($user->status === 'active') {
            if ($user->hasRole('admin') && $this->otherActiveAdmins($user) === 0) {
                return back()->withErrors(['user' => 'There must be at least one active admin.']);
            }

            DB::transaction(function () use ($user): void {
                $user->forceFill(['status' => 'suspended'])->save();
                $this->revokeEditorialGrantsIfIneligible($user);
            });
            $this->endSessions($user);

            return back()->with('success', "{$user->name} was suspended and logged out.");
        }

        $user->forceFill(['status' => 'active'])->save();

        return back()->with('success', "{$user->name} is active again.");
    }

    public function resetPassword(User $user)
    {
        $this->guardTarget($user);

        $plain = Str::password(10, symbols: false);
        $user->forceFill(['password' => Hash::make($plain), 'remember_token' => Str::random(60)])->save();

        if ($user->id !== auth()->id()) {
            $this->endSessions($user);
        }

        return back()
            ->with('success', "Password reset for {$user->name}.")
            ->with('credentials', ['name' => $user->name, 'email' => $user->email, 'password' => $plain]);
    }

    public function toggleWebsite(User $user)
    {
        $staff = StaffMember::where('user_id', $user->id)->first();

        if (! $staff) {
            return back()->withErrors(['user' => 'This user has no staff profile. Edit the user and tick "This person works at the school".']);
        }

        $staff->update(['show_on_website' => ! $staff->show_on_website]);

        return back()->with('success', $staff->show_on_website
            ? "{$user->name}'s legacy website marker was enabled. It does not publish a public Team profile."
            : "{$user->name}'s legacy website marker was disabled. Public Team profiles are managed separately.");
    }

    public function destroy(User $user)
    {
        $this->guardTarget($user);

        if ($user->id === auth()->id()) {
            return back()->withErrors(['user' => 'You cannot delete your own account.']);
        }
        if ($user->hasRole('admin') && $this->otherActiveAdmins($user) === 0) {
            return back()->withErrors(['user' => 'There must be at least one active admin.']);
        }

        $photos = array_filter([$user->photo]);
        DB::transaction(function () use ($user): void {
            $this->revokeEditorialGrantsIfIneligible($user, force: true);
            $user->delete(); // the staff and teacher records stay, only the login link is removed
        });

        foreach ($photos as $p) {
            Photo::deleteIfUnused($p);
        }

        return back()->with('success', 'User deleted. Their staff record (if any) was kept for payroll history.');
    }

    public function csv(Request $request): StreamedResponse
    {
        $rows = $this->filtered($request)
            ->when(in_array($request->tab, ['active', 'suspended'], true), fn ($q) => $q->where('status', $request->tab))
            ->orderBy('name')->get();
        $labels = config('permissions.role_labels', []);

        return response()->streamDownload(function () use ($rows, $labels) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows Nepali text correctly

            fputcsv($out, ['Name', 'Email', 'Phone', 'Gender', 'Roles', 'Status', 'Designation', 'On website', 'Joined', 'Last login']);

            foreach ($rows as $u) {
                fputcsv($out, [
                    $u->name, $u->email, $u->phone, $u->gender,
                    $u->userRoles->pluck('role')->map(fn ($r) => $labels[$r] ?? $r)->implode(', '),
                    $u->status,
                    $u->staff->designation ?? '',
                    $u->staff ? ($u->staff->show_on_website ? 'Yes' : 'No') : '',
                    $u->created_at?->format('Y-m-d'),
                    $u->last_login_at?->format('Y-m-d H:i'),
                ]);
            }

            fclose($out);
        }, 'users-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function editorialAccess(User $user)
    {
        abort_unless(auth()->user()->status === 'active' && auth()->user()->hasRole('admin'), 403);

        return view('admin.users.editorial-access', [
            'recipient' => $user,
            'groups' => PublicEditorialGrant::GROUPS,
            'eligible' => PublicEditorialGrant::isEligibleRecipient($user),
            'granted' => PublicEditorialGrant::query()->where('user_id', $user->id)->pluck('permission')->all(),
            'events' => PublicEditorialGrantEvent::query()
                ->where('target_user_id', $user->id)
                ->with('actor:id,name')
                ->latest('id')
                ->limit(30)
                ->get(),
        ]);
    }

    public function updateEditorialAccess(Request $request, User $user, PublicEditorialGrants $grants)
    {
        abort_unless($request->user()->status === 'active' && $request->user()->hasRole('admin'), 403);

        $validated = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(PublicEditorialGrant::allowedPermissions())],
        ]);

        $grants->sync($request->user(), $user, $validated['permissions'] ?? []);

        return redirect()->route('admin.users.editorial-access', $user)
            ->with('success', 'Public editorial access updated.');
    }

    // ---------------------------------------------------------------------

    /** Create or update the account, roles, photo, teacher link and staff profile in one transaction. */
    private function save(Request $request, ?User $user): array
    {
        $data = $request->validate($this->rules($user));
        $isNew = $user === null;
        $plain = null;

        DB::transaction(function () use ($request, $data, &$user, $isNew, &$plain) {
            $roles = $this->resolveRoles($request, $user);

            if ($isNew) {
                $plain = ! empty($data['password']) ? $data['password'] : Str::password(10, symbols: false);
                $user = new User;
                $user->forceFill([
                    'password' => Hash::make($plain),
                    'status' => 'active',
                    'email_verified_at' => now(), // admin-created accounts are verified, the routes require it
                ]);
            }

            $user->forceFill([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'gender' => $data['gender'] ?? null,
            ])->save();

            $this->syncRoles($user, $roles);
            $this->revokeEditorialGrantsIfIneligible($user);

            $teacherId = in_array('teacher', $roles, true) ? ($data['teacher_id'] ?? null) : null;
            $this->linkTeacher($user, $teacherId ? (int) $teacherId : null);
            $this->applyPhoto($request, $user);
            $this->applyStaff($request, $user, $data, $teacherId ? (int) $teacherId : null);
        });

        return [$user, $plain];
    }

    private function rules(?User $user): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'gender' => ['nullable', Rule::in(array_keys(self::GENDERS))],
            'password' => $user ? ['nullable'] : ['nullable', 'string', 'min:8', 'max:72'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string'],
            'teacher_id' => ['nullable', 'exists:teachers,id'],
            'staff_member_id' => ['nullable', 'exists:staff_members,id'],
            'designation' => ['nullable', 'string', 'max:100'],
            'staff_type' => ['nullable', Rule::in(array_keys(StaffMember::TYPES))],
            'joined_on' => ['nullable', 'date'],
            'qualification' => ['nullable', 'string', 'max:150'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'team_group' => ['nullable', Rule::in(array_keys(StaffMember::GROUPS))],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    private function resolveRoles(Request $request, ?User $user): array
    {
        $current = $user ? $user->userRoles()->pluck('role')->all() : [];

        if (! Gate::allows('users.roles')) {
            return $current;
        }

        $roles = array_values(array_intersect(array_keys(config('permissions.roles', [])), (array) $request->input('roles', [])));
        $hadAdmin = in_array('admin', $current, true);
        $wantsAdmin = in_array('admin', $roles, true);

        if ($hadAdmin !== $wantsAdmin) {
            if (! $request->user()->hasRole('admin')) {
                throw ValidationException::withMessages(['roles' => 'Only an admin can give or remove the Admin role.']);
            }
            if ($hadAdmin && $user) {
                if ($user->id === auth()->id()) {
                    throw ValidationException::withMessages(['roles' => 'You cannot remove your own Admin role.']);
                }
                if ($this->otherActiveAdmins($user) === 0) {
                    throw ValidationException::withMessages(['roles' => 'There must be at least one active admin.']);
                }
            }
        }

        return $roles;
    }

    private function syncRoles(User $user, array $roles): void
    {
        UserRole::where('user_id', $user->id)->whereNotIn('role', $roles)->delete();

        foreach ($roles as $role) {
            UserRole::firstOrCreate(['user_id' => $user->id, 'role' => $role]);
        }
    }

    private function linkTeacher(User $user, ?int $teacherId): void
    {
        if ($teacherId && Teacher::where('id', $teacherId)->whereNotNull('user_id')->where('user_id', '!=', $user->id)->exists()) {
            throw ValidationException::withMessages(['teacher_id' => 'That teacher already has a login.']);
        }

        // query-builder updates are not blocked by $fillable
        Teacher::where('user_id', $user->id)
            ->when($teacherId, fn ($q) => $q->where('id', '!=', $teacherId))
            ->update(['user_id' => null]);

        if ($teacherId) {
            Teacher::whereKey($teacherId)->update(['user_id' => $user->id]);
        }
    }

    private function applyPhoto(Request $request, User $user): void
    {
        $staff = StaffMember::where('user_id', $user->id)->first();
        $old = array_values(array_unique(array_filter([$user->photo, $staff?->photo])));

        if ($request->hasFile('photo')) {
            $path = Photo::store($request->file('photo'));
        } elseif ($request->boolean('remove_photo')) {
            $path = null;
        } else {
            return;
        }

        $user->forceFill(['photo' => $path])->save();
        $staff?->forceFill(['photo' => $path])->save();

        foreach ($old as $p) {
            Photo::deleteIfUnused($p);
        }
    }

    private function applyStaff(Request $request, User $user, array $data, ?int $teacherId): void
    {
        $current = StaffMember::where('user_id', $user->id)->first();

        if (! $request->boolean('is_staff')) {
            // Keep the payroll record and clear only the legacy marker. Phase 4B
            // public Team visibility is independently revision-backed.
            $current?->update(['user_id' => null, 'show_on_website' => false]);

            return;
        }

        $staff = $current;

        if (! $staff && ! empty($data['staff_member_id'])) {
            $staff = StaffMember::whereNull('user_id')->find($data['staff_member_id']);
            if (! $staff) {
                throw ValidationException::withMessages(['staff_member_id' => 'That staff record already belongs to another login.']);
            }
        }

        if (! $staff && $teacherId) {
            $existing = StaffMember::where('teacher_id', $teacherId)->first();
            if ($existing && $existing->user_id && $existing->user_id !== $user->id) {
                throw ValidationException::withMessages(['teacher_id' => 'That teacher already belongs to another login.']);
            }
            $staff = $existing;
        }

        $staff ??= new StaffMember(['status' => 'active', 'basic_salary' => 0]);

        $tid = $staff->teacher_id;
        if (! $tid && $teacherId && ! StaffMember::where('teacher_id', $teacherId)->where('id', '!=', $staff->id ?? 0)->exists()) {
            $tid = $teacherId;
        }

        $photo = $user->photo ?: $staff->photo;

        $staff->forceFill([
            'user_id' => $user->id,
            'teacher_id' => $tid,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'gender' => $user->gender,
            'designation' => $data['designation'] ?? null,
            'type' => $data['staff_type'] ?? 'non_teaching',
            'joined_on' => $data['joined_on'] ?? null,
            'qualification' => $data['qualification'] ?? null,
            'bio' => $data['bio'] ?? null,
            'photo' => $photo,
            'show_on_website' => $request->boolean('show_on_website'),
            'team_group' => $data['team_group'] ?? 'teaching',
            'display_order' => $data['display_order'] ?? 0,
        ])->save();

        if ($photo && $user->photo !== $photo) {
            $user->forceFill(['photo' => $photo])->save();
        }
    }

    private function otherActiveAdmins(User $except): int
    {
        return User::where('id', '!=', $except->id)
            ->where('status', 'active')
            ->whereHas('userRoles', fn ($q) => $q->where('role', 'admin'))
            ->count();
    }

    private function guardTarget(User $target): void
    {
        abort_if(
            $target->hasRole('admin') && ! auth()->user()->hasRole('admin'),
            403,
            'Only an admin can change an admin account.'
        );
    }

    private function revokeEditorialGrantsIfIneligible(User $target, bool $force = false): void
    {
        if (PublicEditorialGrant::query()->where('user_id', $target->id)->exists()
            && ($force || ! PublicEditorialGrant::isEligibleRecipient($target->fresh()))) {
            app(PublicEditorialGrants::class)->sync(auth()->user(), $target, []);
        }
    }

    private function endSessions(User $user): void
    {
        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
    }
}
