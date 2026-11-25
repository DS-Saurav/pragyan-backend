@extends('layouts.admin')

@section('content')
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
        }
    </style>

    @php
        $me = auth()->user();
        $isAdmin = $me->hasRole('admin');
        $canManage = $me->can('users.manage');
        $canRoles = $me->can('users.roles');
        $labels = config('permissions.role_labels', []);
        $roleColor = [
            'admin' => 'bg-rose-100 text-rose-800',
            'principal' => 'bg-purple-100 text-purple-800',
            'academic_coordinator' => 'bg-indigo-100 text-indigo-800',
            'discipline_coordinator' => 'bg-orange-100 text-orange-800',
            'eca_coordinator' => 'bg-pink-100 text-pink-800',
            'teacher' => 'bg-sky-100 text-sky-800',
            'librarian' => 'bg-teal-100 text-teal-800',
            'accountant' => 'bg-amber-100 text-amber-800',
            'parent' => 'bg-fuchsia-100 text-fuchsia-800',
            'student' => 'bg-emerald-100 text-emerald-800',
        ];
        $tabs = [
            'all' => ['ALL', 'bg-slate-900 text-white'],
            'active' => ['ACTIVE', 'bg-emerald-600 text-white'],
            'suspended' => ['SUSPENDED', 'bg-rose-500 text-white'],
        ];
        $creds = session('credentials');
    @endphp

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8">

        @if (session('success'))
            <div
                class="no-print mb-4 bg-emerald-100 border border-emerald-400 text-emerald-700 px-4 py-3 rounded-lg text-sm font-medium">
                {{ session('success') }}</div>
        @endif

        @if ($creds)
            <div class="no-print mb-4 bg-amber-50 border-2 border-amber-400 text-amber-900 px-4 py-3 rounded-lg text-sm">
                <p class="mb-1 flex items-center gap-2 font-bold"><i class="bi bi-key"></i> Login details for {{ $creds['name'] }} — shown only once</p>
                <div class="flex flex-wrap items-center gap-x-6 gap-y-1 font-mono text-xs">
                    <span>Email: <b>{{ $creds['email'] }}</b></span>
                    <span>Password: <b id="credPass">{{ $creds['password'] }}</b></span>
                    <button type="button" id="copyCred"
                        class="px-3 py-1 bg-amber-500 text-white text-[11px] font-bold rounded-md font-sans">Copy</button>
                </div>
                <p class="text-[11px] mt-1">Give these to the person and ask them to change the password in My Account.</p>
            </div>
        @endif

        @if ($errors->any())
            <div class="no-print mb-4 bg-rose-100 border border-rose-400 text-rose-700 px-4 py-3 rounded-lg text-sm">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <x-ui.page-header title="Users & Staff"
            subtitle="Manage logins, roles, and staff records. The legacy “Show on website” marker does not publish anyone; manage public biographies and consent-safe portraits through Public Team Profiles.">
            <x-slot name="actions">
                @canany(['public_team.author', 'public_team.review', 'public_team.publish'])
                    <a href="{{ route('admin.public-team.index') }}" class="no-print ui-btn ui-btn-secondary">
                        <i class="bi bi-people" aria-hidden="true"></i> Public Team profiles
                    </a>
                @endcanany
                @if ($canManage)
                    <button type="button" id="openCreateBtn" class="no-print ui-btn ui-btn-primary">
                        <i class="bi bi-person-plus"></i> Add user
                    </button>
                @endif
            </x-slot>
        </x-ui.page-header>

        <!-- Stat cards -->
        <div class="no-print grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
            @foreach ([['Total users', $stats['total'], 'slate', 'bi-people'], ['Active', $stats['active'], 'emerald', 'bi-person-check'], ['Suspended', $stats['suspended'], 'rose', 'bi-person-x'], ['Legacy website markers', $stats['website'], 'sky', 'bi-globe2'], ['Users with no role', $stats['no_role'], 'amber', 'bi-person-dash']] as [$label, $value, $tone, $icon])
                <x-ui.stat-card :label="$label" :value="$value" :tone="$tone" :icon="$icon" />
            @endforeach
        </div>

        @if ($stats['staff_no_login'] > 0)
            <p class="no-print flex items-start gap-2 text-[11px] text-slate-500">
                <i class="bi bi-info-circle mt-0.5"></i>
                <span>{{ $stats['staff_no_login'] }} active staff in Payroll have no login. That is fine for support staff.
                    To give someone a login, click <b>Add user</b> and link their staff record.</span>
            </p>
        @endif

        <!-- Status tabs -->
        <div class="no-print ui-tabs">
            @foreach ($tabs as $key => [$label, $activeClass])
                <a href="{{ route('admin.users.index', array_merge(request()->except('page', 'tab'), ['tab' => $key])) }}"
                    @class(['ui-tab', 'ui-tab-active' => $tab === $key])>
                    {{ $label }} ({{ $counts[$key] ?? 0 }})
                </a>
            @endforeach
        </div>

        <div class="bg-white shadow-sm sm:rounded-xl p-6 border border-gray-100">

            <div class="no-print flex flex-col xl:flex-row justify-between gap-4 mb-5 border-b pb-4">
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('admin.users.csv', request()->query()) }}" class="ui-btn ui-btn-secondary ui-btn-sm">
                        <i class="bi bi-filetype-csv"></i> CSV
                    </a>
                    <button type="button" onclick="window.print()" class="ui-btn ui-btn-secondary ui-btn-sm">
                        <i class="bi bi-printer"></i> Print / PDF
                    </button>
                </div>

                <form method="GET" action="{{ route('admin.users.index') }}" class="flex flex-wrap items-center gap-2">
                    <input type="hidden" name="tab" value="{{ $tab }}">

                    <select name="role" onchange="this.form.submit()"
                        class="text-xs border border-gray-300 rounded-lg p-2">
                        <option value="">All roles</option>
                        @foreach ($roleKeys as $r)
                            <option value="{{ $r }}" @selected(request('role') === $r)>{{ $labels[$r] ?? $r }}
                            </option>
                        @endforeach
                        <option value="none" @selected(request('role') === 'none')>No role</option>
                    </select>

                    <select name="gender" onchange="this.form.submit()"
                        class="text-xs border border-gray-300 rounded-lg p-2">
                        <option value="">All genders</option>
                        @foreach ($genders as $v => $l)
                            <option value="{{ $v }}" @selected(request('gender') === $v)>{{ $l }}</option>
                        @endforeach
                    </select>

                    <select name="staff" onchange="this.form.submit()"
                        class="text-xs border border-gray-300 rounded-lg p-2">
                        <option value="">Everyone</option>
                        <option value="staff" @selected(request('staff') === 'staff')>School staff</option>
                        <option value="other" @selected(request('staff') === 'other')>Not staff</option>
                    </select>

                    <select name="per_page" onchange="this.form.submit()"
                        class="text-xs border border-gray-300 rounded-lg p-2">
                        @foreach ([10, 25, 50, 100] as $n)
                            <option value="{{ $n }}" @selected($perPage === $n)>{{ $n }} / page
                            </option>
                        @endforeach
                    </select>

                    <input type="text" name="search" value="{{ request('search') }}"
                        placeholder="Name, email, phone..."
                        class="text-xs border border-gray-300 rounded-lg p-2 w-48 focus:ring-2 focus:ring-indigo-500">
                    <button type="submit"
                        class="px-3 py-2 bg-slate-900 text-white text-xs font-bold rounded-lg hover:bg-slate-800">Search</button>

                    @if (request()->hasAny(['search', 'role', 'gender', 'staff']))
                        <a href="{{ route('admin.users.index', ['tab' => $tab]) }}"
                            class="px-3 py-2 bg-gray-200 text-xs font-bold rounded-lg">Reset</a>
                    @endif
                </form>
            </div>

            <div class="overflow-x-auto rounded-xl border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                    <thead class="bg-slate-900 text-white uppercase font-bold tracking-wider">
                        <tr>
                            <th class="px-4 py-3">User</th>
                            <th class="px-4 py-3">Email</th>
                            <th class="px-4 py-3">Mobile</th>
                            <th class="px-4 py-3">Role</th>
                            <th class="px-4 py-3">Gender</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Joined</th>
                            <th class="px-4 py-3">Last login</th>
                            <th class="px-4 py-3 text-right no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($users as $u)
                            @php
                                $avatar = $u->avatarUrl();
                                $st = $u->staff;
                                $isSelf = $u->id === $me->id;
                                $userRoles = $u->userRoles->pluck('role')->all();
                                $locked = in_array('admin', $userRoles, true) && !$isAdmin; // only admins may touch admins
                                $payload = [
                                    'id' => $u->id,
                                    'name' => $u->name,
                                    'email' => $u->email,
                                    'phone' => $u->phone,
                                    'gender' => $u->gender,
                                    'photo_url' => $avatar,
                                    'roles' => $userRoles,
                                    'teacher_id' => $teacherByUser[$u->id] ?? null,
                                    'is_staff' => (bool) $st,
                                    'staff_member_id' => $st?->id,
                                    'designation' => $st?->designation,
                                    'staff_type' => $st?->type,
                                    'joined_on' => $st?->joined_on?->format('Y-m-d'),
                                    'qualification' => $st?->qualification,
                                    'bio' => $st?->bio,
                                    'show_on_website' => (bool) $st?->show_on_website,
                                    'team_group' => $st?->team_group,
                                    'display_order' => $st?->display_order,
                                ];
                            @endphp
                            <tr class="hover:bg-gray-50/50 {{ $u->status === 'suspended' ? 'opacity-70' : '' }}"
                                data-user="{{ json_encode($payload) }}">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        @if ($avatar)
                                            <img src="{{ $avatar }}" alt=""
                                                class="w-10 h-10 rounded-full object-cover border border-gray-200">
                                        @else
                                            <span
                                                class="w-10 h-10 rounded-full bg-slate-900 text-white text-[11px] font-bold flex items-center justify-center">{{ $u->initials() }}</span>
                                        @endif
                                        <div>
                                            <div class="font-bold text-gray-900">{{ $u->name }}@if ($isSelf)
                                                    <span class="ml-1 text-[10px] text-indigo-600">(you)</span>
                                                @endif
                                            </div>
                                            <div class="text-[11px] text-gray-400">
                                                {{ $st?->designation ?: ($st ? 'Staff' : '') }}
                                                @if ($st?->show_on_website)
                                                    <span class="ml-1 inline-flex items-center gap-1 rounded border border-sky-100 bg-sky-50 px-1.5 py-0.5 text-[10px] font-bold text-sky-700">
                                                        <i class="bi bi-globe2"></i> legacy marker
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-gray-700">{{ $u->email }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-gray-700">{{ $u->phone ?: '—' }}</td>
                                <td class="px-4 py-3">
                                    @forelse ($userRoles as $r)
                                        <span
                                            class="inline-block mr-1 mb-1 px-2.5 py-1 rounded-md text-[10px] font-bold uppercase {{ $roleColor[$r] ?? 'bg-gray-100 text-gray-700' }}">{{ $labels[$r] ?? $r }}</span>
                                    @empty
                                        <span
                                            class="px-2.5 py-1 rounded-md text-[10px] font-bold uppercase bg-gray-100 text-gray-500">No
                                            role</span>
                                    @endforelse
                                </td>
                                <td class="px-4 py-3 capitalize">{{ $u->gender ?: '—' }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase {{ $u->status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">{{ $u->status }}</span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $u->created_at?->format('Y-m-d') }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-gray-500">
                                    {{ $u->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap space-x-1 no-print">
                                    @if ($isAdmin)
                                        <a href="{{ route('admin.users.editorial-access', $u) }}"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-blue-50 hover:text-blue-700"
                                            title="Public editorial access" aria-label="Public editorial access for {{ $u->name }}">
                                            <i class="bi bi-pencil-square" aria-hidden="true"></i>
                                        </a>
                                    @endif
                                    @if ($canManage && !$locked)
                                        <form action="{{ route('admin.users.reset-password', $u) }}" method="POST"
                                            class="inline"
                                            onsubmit="return confirm('Reset the password of {{ addslashes($u->name) }}? They will be logged out and you will get a new temporary password.');">
                                            @csrf <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-amber-50 hover:text-amber-600"
                                                title="Reset password" aria-label="Reset password"><i class="bi bi-key"></i></button>
                                        </form>

                                        @unless ($isSelf)
                                            <form action="{{ route('admin.users.status', $u) }}" method="POST"
                                                class="inline"
                                                onsubmit="return confirm('{{ $u->status === 'active' ? 'Suspend' : 'Activate' }} {{ addslashes($u->name) }}?');">
                                                @csrf <button type="submit"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 {{ $u->status === 'active' ? 'hover:bg-rose-50 hover:text-rose-600' : 'hover:bg-emerald-50 hover:text-emerald-600' }}"
                                                    title="{{ $u->status === 'active' ? 'Suspend' : 'Activate' }}"
                                                    aria-label="{{ $u->status === 'active' ? 'Suspend' : 'Activate' }}">
                                                    <i class="bi {{ $u->status === 'active' ? 'bi-slash-circle' : 'bi-check2-circle' }}"></i>
                                                </button>
                                            </form>
                                        @endunless

                                        @if ($st)
                                            <form action="{{ route('admin.users.website', $u) }}" method="POST"
                                                class="inline">
                                                @csrf <button type="submit"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 {{ $st->show_on_website ? 'text-sky-600' : 'opacity-40' }} hover:bg-sky-50 hover:opacity-100"
                                                    title="{{ $st->show_on_website ? 'Disable legacy website marker' : 'Enable legacy website marker' }}"
                                                    aria-label="{{ $st->show_on_website ? 'Disable legacy website marker' : 'Enable legacy website marker' }}">
                                                    <i class="bi bi-globe2"></i>
                                                </button>
                                            </form>
                                        @endif

                                        <button type="button" class="edit-btn inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-emerald-50 hover:text-emerald-600"
                                            title="Edit" aria-label="Edit user"><i class="bi bi-pencil"></i></button>
                                    @endif

                                    @can('users.delete')
                                        @if (!$isSelf && !$locked)
                                            <form action="{{ route('admin.users.destroy', $u) }}" method="POST"
                                                class="inline"
                                                onsubmit="return confirm('Delete {{ addslashes($u->name) }}? Suspending is safer. Staff records stay for payroll history.');">
                                                @csrf @method('DELETE') <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                                                    title="Delete" aria-label="Delete user"><i class="bi bi-trash"></i></button>
                                            </form>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-8 text-center text-gray-500">No users found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex flex-col md:flex-row justify-between items-center gap-3 text-xs text-gray-500">
                <div>Showing {{ $users->firstItem() ?? 0 }} to {{ $users->lastItem() ?? 0 }} of {{ $users->total() }}
                    entries</div>
                <div class="no-print">{{ $users->links() }}</div>
            </div>
        </div>
    </div>

    @if ($canManage)
        <div id="userModal" style="display:none;"
            class="no-print fixed inset-0 z-50 items-center justify-center bg-black bg-opacity-60 backdrop-blur-sm">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl p-6 max-h-[94vh] overflow-y-auto">
                <div class="flex justify-between items-center pb-3 border-b mb-4">
                    <h3 class="text-md font-bold" id="formTitle">Add User</h3>
                    <button type="button" class="close-modal text-xl" data-target="userModal">&times;</button>
                </div>

                <form id="userForm" method="POST" action="{{ route('admin.users.store') }}"
                    enctype="multipart/form-data" class="space-y-5">
                    @csrf
                    <input type="hidden" name="_method" id="formMethod" value="PUT" disabled>
                    <input type="hidden" name="_mode" id="formMode" value="create">
                    <input type="hidden" name="_edit_id" id="formEditId" value="">

                    <!-- 1 identity -->
                    <div>
                        <p class="text-[11px] font-bold uppercase text-indigo-600 mb-2">1 · Person</p>
                        <div class="flex flex-col md:flex-row gap-4">
                            <div class="flex flex-col items-center gap-2 md:w-40">
                                <div class="flex h-28 w-28 items-center justify-center overflow-hidden rounded-full border-2 border-dashed border-slate-300 bg-slate-100 text-3xl text-slate-400"
                                    id="photoBox"><i class="bi bi-person"></i></div>
                                <label
                                    class="px-3 py-1.5 bg-slate-900 text-white text-[11px] font-bold rounded-md cursor-pointer">
                                    Upload photo
                                    <input type="file" name="photo" id="photoInput"
                                        accept="image/png,image/jpeg,image/webp" class="hidden">
                                </label>
                                <label id="removePhotoWrap"
                                    class="hidden items-center gap-1 text-[11px] text-rose-600"><input type="checkbox"
                                        name="remove_photo" value="1" class="rounded border-gray-300"> Remove
                                    photo</label>
                                <p class="text-[10px] text-gray-400 text-center">JPG, PNG or WebP up to 4 MB. It is cropped
                                    to a square automatically.</p>
                            </div>

                            <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div class="md:col-span-2">
                                    <label class="block text-xs font-bold uppercase mb-1">Full name *</label>
                                    <input type="text" name="name" required maxlength="120"
                                        class="w-full text-xs border rounded-lg p-2.5">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold uppercase mb-1">Email (login) *</label>
                                    <input type="email" name="email" required maxlength="150"
                                        class="w-full text-xs border rounded-lg p-2.5">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold uppercase mb-1">Mobile</label>
                                    <input type="text" name="phone" maxlength="30"
                                        class="w-full text-xs border rounded-lg p-2.5">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold uppercase mb-1">Gender</label>
                                    <select name="gender" class="w-full text-xs border rounded-lg p-2.5">
                                        <option value="">—</option>
                                        @foreach ($genders as $v => $l)
                                            <option value="{{ $v }}">{{ $l }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div id="passwordWrap">
                                    <label class="block text-xs font-bold uppercase mb-1">Password</label>
                                    <div class="flex gap-2">
                                        <input type="text" name="password" id="passwordInput" minlength="8"
                                            maxlength="72" placeholder="Leave empty to auto-generate"
                                            class="w-full text-xs border rounded-lg p-2.5">
                                        <button type="button" id="genPass"
                                            class="px-3 bg-gray-100 hover:bg-gray-200 text-[11px] font-bold rounded-lg whitespace-nowrap">Generate</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2 roles -->
                    @if ($canRoles)
                        <div>
                            <p class="text-[11px] font-bold uppercase text-indigo-600 mb-2">2 · Roles (what this person can
                                do)</p>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-2 text-xs">
                                @foreach ($roleKeys as $r)
                                    <label class="flex items-center gap-2 border rounded-lg px-3 py-2">
                                        <input type="checkbox" name="roles[]" value="{{ $r }}"
                                            class="role-cb rounded border-gray-300" @disabled($r === 'admin' && !$isAdmin)>
                                        {{ $labels[$r] ?? $r }}
                                    </label>
                                @endforeach
                            </div>
                            <p class="text-[11px] text-gray-400 mt-1">Roles can be combined, for example Teacher +
                                Discipline coordinator. What each role may do is set in <code>config/permissions.php</code>.
                            </p>

                            <div id="teacherWrap" class="mt-3 hidden">
                                <label class="block text-xs font-bold uppercase mb-1">Link to teacher record</label>
                                <select name="teacher_id" id="teacherSel"
                                    class="w-full text-xs border rounded-lg p-2.5"></select>
                                <p class="text-[11px] text-gray-400 mt-1">Choosing a teacher fills in the name, email and
                                    mobile, and connects this login to their classes and subjects.</p>
                            </div>
                        </div>
                    @endif

                    <!-- 3 staff -->
                    <div>
                        <label class="flex items-center gap-2 text-xs font-bold mb-2">
                            <input type="checkbox" name="is_staff" id="isStaff" value="1"
                                class="rounded border-gray-300 text-indigo-600">
                            This person works at the school (staff profile, payroll and website)
                        </label>

                        <div id="staffWrap" class="hidden border rounded-xl p-4 bg-gray-50 space-y-3">
                            <div id="linkStaffWrap">
                                <label class="block text-xs font-bold uppercase mb-1">Link an existing staff record from
                                    Payroll (optional)</label>
                                <select name="staff_member_id" id="staffSel"
                                    class="w-full text-xs border rounded-lg p-2.5"></select>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-xs font-bold uppercase mb-1">Designation</label>
                                    <input type="text" name="designation" maxlength="100"
                                        placeholder="e.g. English Teacher" class="w-full text-xs border rounded-lg p-2.5">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold uppercase mb-1">Staff type</label>
                                    <select name="staff_type" class="w-full text-xs border rounded-lg p-2.5">
                                        @foreach (\App\Models\StaffMember::TYPES as $v => $l)
                                            <option value="{{ $v }}">{{ $l }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold uppercase mb-1">Joined on</label>
                                    <input type="date" name="joined_on"
                                        class="w-full text-xs border rounded-lg p-2.5">
                                </div>
                                <div class="md:col-span-3">
                                    <label class="block text-xs font-bold uppercase mb-1">Qualification</label>
                                    <input type="text" name="qualification" maxlength="150"
                                        placeholder="e.g. M.Ed., B.Sc. CSIT"
                                        class="w-full text-xs border rounded-lg p-2.5">
                                </div>
                                <div class="md:col-span-3">
                                    <label class="block text-xs font-bold uppercase mb-1">Short bio (shown on the
                                        website)</label>
                                    <textarea name="bio" rows="3" maxlength="2000" class="w-full text-xs border rounded-lg p-2.5"></textarea>
                                </div>
                            </div>

                            <div class="border-t pt-3">
                                <label class="flex items-center gap-2 text-xs font-bold mb-2">
                                    <input type="checkbox" name="show_on_website" id="showWeb" value="1"
                                        class="rounded border-gray-300 text-sky-600">
                                    <i class="bi bi-globe2 text-sky-600"></i> Legacy website marker (does not publish a public Team profile)
                                </label>
                                <div id="webFields" class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-bold uppercase mb-1">Group</label>
                                        <select name="team_group" class="w-full text-xs border rounded-lg p-2.5">
                                            @foreach (\App\Models\StaffMember::GROUPS as $v => $l)
                                                <option value="{{ $v }}">{{ $l }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold uppercase mb-1">Order (smaller =
                                            first)</label>
                                        <input type="number" name="display_order" min="0" max="999"
                                            class="w-full text-xs border rounded-lg p-2.5">
                                    </div>
                                </div>
                                <p class="text-[11px] text-gray-400 mt-1">Only the photo, name, designation, qualification
                                    and bio are public. Phone numbers and emails never are.</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-2 pt-2">
                        <button type="button" class="close-modal px-4 py-2 bg-gray-200 text-xs font-bold rounded-lg"
                            data-target="userModal">Cancel</button>
                        <button type="submit"
                            class="px-4 py-2 bg-emerald-600 text-white text-xs font-bold rounded-lg">Save</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <script>
        (function() {
            var copy = document.getElementById('copyCred');
            if (copy) copy.addEventListener('click', function() {
                var t = document.getElementById('credPass').textContent;
                (navigator.clipboard ? navigator.clipboard.writeText(t) : Promise.reject()).then(function() {
                    copy.textContent = 'Copied';
                }, function() {
                    window.prompt('Copy the password:', t);
                });
            });

            var form = document.getElementById('userForm');
            if (!form) return;

            var STORE_URL = @json(route('admin.users.store'));
            var BASE_URL = @json(url('/admin/users'));
            var TEACHERS = @json($teachersJs);
            var FREE_STAFF = @json($freeStaffJs);
            var TEXT = ['name', 'email', 'phone', 'gender', 'designation', 'staff_type', 'joined_on', 'qualification',
                'bio', 'team_group', 'display_order'
            ];

            var photoBox = document.getElementById('photoBox');
            var teacherSel = document.getElementById('teacherSel');
            var staffSel = document.getElementById('staffSel');
            var isStaff = document.getElementById('isStaff');
            var showWeb = document.getElementById('showWeb');
            var editingId = null;

            function showModal(id) {
                var el = document.getElementById(id);
                if (el) el.style.display = 'flex';
            }

            function hideModal(id) {
                var el = document.getElementById(id);
                if (el) el.style.display = 'none';
            }

            function opt(value, text) {
                var o = document.createElement('option');
                o.value = value;
                o.textContent = text;
                return o;
            }

            function roleChecked(r) {
                var c = form.querySelector('.role-cb[value="' + r + '"]');
                return c && c.checked;
            }

            function setPhoto(url) {
                photoBox.innerHTML = '';
                if (url) {
                    var img = document.createElement('img');
                    img.src = url;
                    img.className = 'w-full h-full object-cover';
                    photoBox.appendChild(img);
                } else {
                    photoBox.innerHTML = '<i class="bi bi-person"></i>';
                }
                document.getElementById('removePhotoWrap').style.display = (url && editingId) ? 'flex' : 'none';
            }

            function fillTeachers(selected) {
                if (!teacherSel) return;
                teacherSel.innerHTML = '';
                teacherSel.appendChild(opt('', '— Not linked —'));
                TEACHERS.filter(function(t) {
                        return !t.user_id || String(t.user_id) === String(editingId);
                    })
                    .forEach(function(t) {
                        teacherSel.appendChild(opt(t.id, t.name));
                    });
                teacherSel.value = selected || '';
            }

            function fillStaff() {
                staffSel.innerHTML = '';
                staffSel.appendChild(opt('', '— Create a new staff profile —'));
                FREE_STAFF.forEach(function(s) {
                    staffSel.appendChild(opt(s.id, s.name + (s.designation ? ' · ' + s.designation : '')));
                });
            }

            function sync() {
                var tw = document.getElementById('teacherWrap');
                if (tw) tw.style.display = roleChecked('teacher') ? 'block' : 'none';
                document.getElementById('staffWrap').style.display = isStaff.checked ? 'block' : 'none';
                document.getElementById('linkStaffWrap').style.display = (isStaff.checked && !editingId) ? 'block' :
                    'none';
                document.getElementById('webFields').style.opacity = showWeb.checked ? '1' : '.4';
            }

            function openForm(mode, data, id) {
                data = data || {};
                var edit = mode === 'edit';
                editingId = edit ? id : null;

                form.reset();
                document.getElementById('formTitle').textContent = edit ? 'Edit User' : 'Add User';
                form.setAttribute('action', edit ? BASE_URL + '/' + id : STORE_URL);
                document.getElementById('formMethod').disabled = !edit;
                document.getElementById('formMode').value = mode;
                document.getElementById('formEditId').value = id || '';
                document.getElementById('passwordWrap').style.display = edit ? 'none' : 'block';

                TEXT.forEach(function(n) {
                    var el = form.elements[n];
                    if (el) el.value = (data[n] !== undefined && data[n] !== null) ? data[n] : '';
                });

                var roles = data.roles ? (Array.isArray(data.roles) ? data.roles : Object.keys(data.roles).map(function(
                    k) {
                    return data.roles[k];
                })) : [];
                form.querySelectorAll('.role-cb').forEach(function(c) {
                    c.checked = roles.indexOf(c.value) !== -1;
                });

                var truthy = function(v) {
                    return v === true || v === 1 || v === '1' || v === 'on';
                };
                isStaff.checked = truthy(data.is_staff);
                showWeb.checked = truthy(data.show_on_website);
                if (!form.elements['staff_type'].value) form.elements['staff_type'].value = 'non_teaching';
                if (!form.elements['team_group'].value) form.elements['team_group'].value = 'teaching';

                fillTeachers(data.teacher_id);
                fillStaff();
                setPhoto(data.photo_url);
                sync();
                showModal('userModal');
            }

            // Picking a teacher fills the empty fields
            if (teacherSel) teacherSel.addEventListener('change', function() {
                var t = TEACHERS.filter(function(x) {
                    return String(x.id) === String(teacherSel.value);
                })[0];
                if (!t) return;
                if (!form.elements['name'].value) form.elements['name'].value = t.name;
                if (!form.elements['email'].value) form.elements['email'].value = t.email;
                if (!form.elements['phone'].value) form.elements['phone'].value = t.phone;
            });

            // Ticking "Teacher" also makes the person staff
            form.addEventListener('change', function(e) {
                if (e.target.classList.contains('role-cb') && e.target.value === 'teacher' && e.target
                    .checked) {
                    isStaff.checked = true;
                    form.elements['staff_type'].value = 'teaching';
                }
                sync();
            });

            document.getElementById('photoInput').addEventListener('change', function() {
                var f = this.files[0];
                if (!f) return;
                if (f.size > 4 * 1024 * 1024) {
                    alert('This photo is larger than 4 MB. Please choose a smaller one.');
                    this.value = '';
                    return;
                }
                var r = new FileReader();
                r.onload = function(ev) {
                    setPhoto(ev.target.result);
                    document.getElementById('removePhotoWrap').style.display = 'none';
                };
                r.readAsDataURL(f);
            });

            document.getElementById('genPass').addEventListener('click', function() {
                var chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789',
                    out = '';
                var arr = new Uint32Array(10);
                (window.crypto || window.msCrypto).getRandomValues(arr);
                arr.forEach(function(n) {
                    out += chars[n % chars.length];
                });
                document.getElementById('passwordInput').value = out;
            });

            document.addEventListener('click', function(e) {
                if (e.target.closest('#openCreateBtn')) {
                    openForm('create', {}, null);
                    return;
                }

                var closeBtn = e.target.closest('.close-modal');
                if (closeBtn) {
                    hideModal(closeBtn.getAttribute('data-target'));
                    return;
                }
                if (e.target.id === 'userModal') {
                    hideModal('userModal');
                    return;
                }

                var editBtn = e.target.closest('.edit-btn');
                if (editBtn) {
                    try {
                        var d = JSON.parse(editBtn.closest('tr').dataset.user);
                        openForm('edit', d, d.id);
                    } catch (err) {
                        console.error(err);
                    }
                }
            });

            @if ($errors->any() && old('_mode'))
                var OLD = @json(old());
                openForm(OLD._mode === 'edit' ? 'edit' : 'create', OLD, OLD._edit_id || null);
            @endif
        })();
    </script>
@endsection
