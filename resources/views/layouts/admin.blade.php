<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0b1a4a">

    <title>@yield('title', 'Dashboard') | {{ config('app.name', 'Pragyan Pathashala') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        #sidebarNav::-webkit-scrollbar {
            width: 4px;
        }

        #sidebarNav::-webkit-scrollbar-thumb {
            background: rgba(148, 163, 184, .35);
            border-radius: 4px;
        }

        #sidebar {
            transition: width .2s ease, transform .2s ease;
        }

        @media (min-width: 768px) {
            #sidebar.collapsed {
                width: 4.5rem;
            }

            #sidebar.collapsed .sb-label,
            #sidebar.collapsed .sb-section,
            #sidebar.collapsed .sb-profile-text,
            #sidebar.collapsed .sb-badge,
            #sidebar.collapsed .sb-brand-name,
            #sidebar.collapsed .sb-brand-tile {
                display: none;
            }

            #sidebar.collapsed .sb-item {
                justify-content: center;
                padding-left: 0;
                padding-right: 0;
            }

            #sidebar.collapsed .sb-item i {
                margin-right: 0;
            }

            #sidebar.collapsed .sb-avatar {
                width: 2.5rem;
                height: 2.5rem;
            }

            #sidebar.collapsed .sb-collapse i {
                transform: rotate(180deg);
            }
        }

        /* A semantic dark canvas preserves status colors and media fidelity. */
        html.dark #mainContent {
            background: var(--ui-dark-canvas);
            color: var(--ui-dark-text);
        }

        html.dark #mainContent img,
        html.dark #mainContent video {
            filter: none;
        }
    </style>

    @stack('head')
</head>

<body class="font-sans antialiased bg-slate-100">
    @php
        $schoolName = config('app.name', 'Pragyan Pathashala');
        $user = Auth::user();
        $userRole = $user->roleNames() ? collect($user->roleNames())->map(fn ($role) => config("permissions.role_labels.{$role}", ucfirst($role)))->join(', ') : 'User';
        $isStaff = ! $user->hasRole('student', 'parent');
        $avatar = $user->avatarUrl();
        $badges = $sidebarBadges ?? [];

        $menu = [
            'Overview' => [
                ['label' => 'Dashboard', 'icon' => 'bi-speedometer2', 'route' => 'dashboard', 'pattern' => 'dashboard'],
            ],
            'Records' => [
                ['label' => 'Admissions', 'icon' => 'bi-person-plus', 'route' => 'admin.admissions.index', 'pattern' => 'admin.admissions.*', 'staff' => true, 'permission' => 'admissions.manage'],
                ['label' => 'Students', 'icon' => 'bi-mortarboard', 'route' => 'admin.students.index', 'pattern' => 'admin.students.*', 'staff' => true],
                ['label' => 'Classes & Sections', 'icon' => 'bi-diagram-3', 'route' => 'admin.classes.index', 'pattern' => 'admin.classes.*', 'staff' => true],
                ['label' => 'Parents', 'icon' => 'bi-people', 'route' => 'admin.parents.index', 'pattern' => 'admin.parents.*', 'staff' => true],
                ['label' => 'Assets', 'icon' => 'bi-box', 'route' => 'admin.assets.index', 'pattern' => 'admin.assets.*', 'staff' => true],
                ['label' => 'Stock / Inventory', 'icon' => 'bi-boxes', 'route' => 'admin.inventory.index', 'pattern' => 'admin.inventory.*', 'staff' => true],
                ['label' => 'Subjects', 'icon' => 'bi-book', 'route' => 'admin.subjects.index', 'pattern' => 'admin.subjects.*', 'staff' => true],
                ['label' => 'Teachers', 'icon' => 'bi-person-workspace', 'route' => 'admin.teachers.index', 'pattern' => 'admin.teachers.*', 'staff' => true],
                ['label' => 'Substitutes', 'icon' => 'bi-arrow-repeat', 'route' => 'admin.substitutes.index', 'pattern' => 'admin.substitutes.*', 'staff' => true],
            ],
            'Library' => [
                ['label' => 'Books Catalog', 'icon' => 'bi-collection', 'route' => 'admin.library.books.index', 'pattern' => 'admin.library.books.*', 'permission' => 'library.manage'],
                ['label' => 'Issue / Return', 'icon' => 'bi-journal-bookmark', 'route' => 'admin.library.circulation.index', 'pattern' => 'admin.library.circulation.*', 'permission' => 'library.manage'],
            ],
            'Daily' => [
                ['label' => 'Timetable', 'icon' => 'bi-calendar3', 'route' => 'admin.timetable.index', 'pattern' => 'admin.timetable.*', 'staff' => true],
                ['label' => 'Attendance', 'icon' => 'bi-person-check', 'route' => 'admin.attendance.index', 'pattern' => 'admin.attendance.*', 'staff' => true],
                ['label' => 'School Notices', 'icon' => 'bi-megaphone', 'route' => 'admin.notices.index', 'pattern' => 'admin.notices.*', 'staff' => true, 'permissions' => ['notices.manage', 'notices.author', 'notices.review', 'notices.publish']],
                ['label' => 'PTM', 'icon' => 'bi-person-lines-fill', 'route' => 'admin.ptm.index', 'pattern' => 'admin.ptm.*', 'staff' => true],
                ['label' => 'Calendar', 'icon' => 'bi-calendar-event', 'route' => 'admin.calendar.index', 'pattern' => 'admin.calendar.*', 'staff' => true, 'permission' => 'calendar.view'],
                ['label' => 'Lesson Planning', 'icon' => 'bi-pencil-square', 'route' => 'admin.lesson-plans.index', 'pattern' => 'admin.lesson-plans.*', 'permission' => 'lessonplans.view'],
                ['label' => 'Teaching Logbook', 'icon' => 'bi-journal-text', 'route' => 'admin.teaching-logs.index', 'pattern' => 'admin.teaching-logs.*', 'permission' => 'teachinglogs.view'],
            ],
            'Academic' => [
                ['label' => 'Exams', 'icon' => 'bi-clipboard-check', 'route' => 'admin.exams.index', 'pattern' => 'admin.exams.*', 'permission' => 'exams.view'],
                ['label' => 'Results / Marks', 'icon' => 'bi-award', 'route' => 'admin.results.index', 'pattern' => 'admin.results.*', 'permission' => 'results.view'],
                ['label' => 'Hall Tickets', 'icon' => 'bi-ticket-detailed', 'route' => 'admin.hall-tickets.index', 'pattern' => 'admin.hall-tickets.*', 'permission' => 'exams.manage'],
                ['label' => 'Discipline', 'icon' => 'bi-shield-check', 'route' => 'admin.discipline.index', 'pattern' => 'admin.discipline.*', 'permission' => 'discipline.view'],
                ['label' => 'Conduct', 'icon' => 'bi-patch-check', 'route' => 'admin.conduct.index', 'pattern' => 'admin.conduct.*', 'permission' => 'conduct.view'],
                ['label' => 'Activities', 'icon' => 'bi-trophy', 'route' => 'admin.activities.index', 'pattern' => 'admin.activities.*', 'permission' => 'activities.view'],
            ],
            'Finance' => [
                ['label' => 'Fees Collection', 'icon' => 'bi-wallet2', 'route' => 'admin.fees.index', 'pattern' => 'admin.fees.*', 'permission' => 'fees.view'],
                ['label' => 'Fee Structure', 'icon' => 'bi-sliders', 'route' => 'admin.fee-structure.index', 'pattern' => 'admin.fee-structure.*', 'permission' => 'fees.view'],
                ['label' => 'Payroll / Salary', 'icon' => 'bi-cash-stack', 'route' => 'admin.payroll.index', 'pattern' => 'admin.payroll.*', 'permission' => 'payroll.view'],
                ['label' => 'Daily Accounts', 'icon' => 'bi-bank', 'route' => 'admin.accounts.index', 'pattern' => 'admin.accounts.*', 'permission' => 'accounts.view'],
            ],
            'Support' => [
                ['label' => 'Helpdesk', 'icon' => 'bi-headset', 'route' => 'admin.helpdesk.index', 'pattern' => 'admin.helpdesk.*', 'permission' => 'helpdesk.view', 'badge' => 'helpdesk'],
                ['label' => 'Complaints', 'icon' => 'bi-chat-left-text', 'route' => 'admin.complaints.index', 'pattern' => 'admin.complaints.*', 'permission' => 'complaints.view', 'badge' => 'complaints'],
                ['label' => 'Documents', 'icon' => 'bi-folder2-open', 'route' => 'admin.documents.index', 'pattern' => 'admin.documents.*', 'permission' => 'documents.view'],
                ['label' => 'Notice Board', 'icon' => 'bi-newspaper', 'route' => 'public.notices', 'pattern' => 'public.notices'],
            ],
            'Administration' => [
                ['label' => 'Users / Staff', 'icon' => 'bi-people', 'route' => 'admin.users.index', 'pattern' => 'admin.users.*', 'permission' => 'users.view'],
                ['label' => 'School Settings', 'icon' => 'bi-gear', 'route' => 'admin.settings.index', 'pattern' => 'admin.settings.index|admin.settings.marksheet*', 'permission' => 'settings.view'],
                ['label' => 'Website Content', 'icon' => 'bi-images', 'route' => 'admin.settings.website.index', 'pattern' => 'admin.settings.website.*', 'permission' => 'public_homepage.manage'],
                ['label' => 'Public Photographs', 'icon' => 'bi-image', 'route' => 'admin.public-photos.index', 'pattern' => 'admin.public-photos.*', 'permission' => 'public_homepage.manage'],
                ['label' => 'Public Admissions', 'icon' => 'bi-megaphone', 'route' => 'admin.public-admissions.index', 'pattern' => 'admin.public-admissions.*', 'permission' => 'public_admissions.manage'],
                ['label' => 'Media Library', 'icon' => 'bi-images', 'route' => 'admin.media.index', 'pattern' => 'admin.media.*', 'permissions' => ['media.view', 'media.upload', 'media.review', 'media.publish']],
                ['label' => 'Public News', 'icon' => 'bi-newspaper', 'route' => 'admin.news.index', 'pattern' => 'admin.news.*', 'permissions' => ['news.author', 'news.review', 'news.publish']],
                ['label' => 'Public Gallery', 'icon' => 'bi-images', 'route' => 'admin.gallery.index', 'pattern' => 'admin.gallery.*', 'permissions' => ['gallery.author', 'gallery.review', 'gallery.publish']],
                ['label' => 'Public Pages', 'icon' => 'bi-file-earmark-richtext', 'route' => 'admin.public-pages.index', 'pattern' => 'admin.public-pages.*', 'permissions' => ['public_pages.author', 'public_pages.review', 'public_pages.publish']],
                ['label' => 'Public Team', 'icon' => 'bi-people', 'route' => 'admin.public-team.index', 'pattern' => 'admin.public-team.*', 'permissions' => ['public_team.author', 'public_team.review', 'public_team.publish']],
                ['label' => 'Public Identity & Visit', 'icon' => 'bi-geo-alt', 'route' => 'admin.public-identity.index', 'pattern' => 'admin.public-identity.*', 'permission' => 'public_identity.manage'],
            ],
            'Account' => [
                ['label' => 'My Account', 'icon' => 'bi-person-circle', 'route' => 'profile.edit', 'pattern' => 'profile.*'],
                ['label' => 'About App', 'icon' => 'bi-info-circle', 'route' => 'admin.about', 'pattern' => 'admin.about', 'permission' => 'settings.manage'],
            ],
        ];

        $visibleMenu = [];
        foreach ($menu as $section => $items) {
            $filtered = array_values(array_filter($items, function ($item) use ($user, $isStaff) {
                if (! empty($item['staff']) && ! $isStaff) {
                    return false;
                }

                if (! empty($item['permission']) && ! $user->hasPermission($item['permission'])) {
                    return false;
                }

                if (! empty($item['permissions']) && ! array_filter(
                    $item['permissions'],
                    fn (string $permission): bool => $user->hasPermission($permission),
                )) {
                    return false;
                }

                return true;
            }));

            if ($filtered) {
                $visibleMenu[$section] = $filtered;
            }
        }
    @endphp

    <a href="#mainContent"
        class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[60] focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-bold focus:text-slate-900 focus:shadow-lg">
        Skip to content
    </a>

    <div class="flex min-h-screen">

        <div id="sidebarBackdrop"
            class="fixed inset-0 z-40 hidden bg-slate-900/60 backdrop-blur-sm md:hidden" aria-hidden="true"></div>

        <aside id="sidebar" aria-label="Primary navigation"
            class="print:hidden fixed inset-y-0 left-0 z-50 flex h-screen w-64 shrink-0 -translate-x-full flex-col overflow-hidden border-r border-white/10 bg-[#0b1a4a] text-slate-300 md:sticky md:top-0 md:z-auto md:translate-x-0">

            <div class="flex h-16 shrink-0 items-center justify-between border-b border-white/10 px-4">
                <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-2.5">
                    <span
                        class="sb-brand-tile flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/10 text-white ring-1 ring-white/15">
                        <i class="bi bi-buildings text-lg"></i>
                    </span>
                    <span class="sb-brand-name truncate text-[15px] font-bold text-white">{{ $schoolName }}</span>
                </a>
                <div class="flex items-center gap-1">
                    <button type="button" id="sidebarClose" aria-label="Close navigation"
                        class="flex h-8 w-8 items-center justify-center rounded-md bg-white/10 text-slate-200 hover:bg-white/20 md:hidden">
                        <i class="bi bi-x-lg text-xs"></i>
                    </button>
                    <button type="button" id="collapseBtn" title="Collapse sidebar" aria-label="Collapse sidebar"
                        aria-controls="sidebar" aria-expanded="true"
                        class="sb-collapse hidden h-7 w-7 shrink-0 items-center justify-center rounded-md bg-white/10 text-slate-200 hover:bg-white/20 md:flex">
                        <i class="bi bi-chevron-left text-xs transition"></i>
                    </button>
                </div>
            </div>

            <div class="flex shrink-0 flex-col items-center py-5">
                @if ($avatar)
                    <img src="{{ $avatar }}" alt="" class="sb-avatar h-[72px] w-[72px] rounded-full border-2 border-white/25 object-cover">
                @else
                    <div class="sb-avatar flex h-[72px] w-[72px] items-center justify-center rounded-full border-2 border-white/25 bg-white/10">
                        <i class="bi bi-person-fill text-3xl text-white"></i>
                    </div>
                @endif
                <div class="sb-profile-text mt-3 px-3 text-center">
                    <p class="truncate text-sm font-bold text-white">{{ $user->name }}</p>
                    <span class="mt-1 inline-block max-w-[13rem] truncate rounded-full bg-white/10 px-3 py-0.5 text-[11px] text-slate-300">
                        {{ $userRole }}
                    </span>
                </div>
            </div>

            <nav id="sidebarNav" aria-label="Main navigation" class="flex-1 overflow-y-auto px-3 pb-3 text-[13px] font-medium">
                @foreach ($visibleMenu as $section => $items)
                    <p class="sb-section px-3 pb-1.5 pt-4 text-[10px] font-semibold uppercase tracking-widest text-white/45">
                        {{ $section }}
                    </p>
                    @foreach ($items as $item)
                        @php
                            $href = Route::has($item['route']) ? route($item['route']) : '#';
                            $isActive = request()->routeIs($item['pattern']);
                            $badge = ! empty($item['badge']) ? ($badges[$item['badge']] ?? null) : null;
                        @endphp
                        <a href="{{ $href }}" title="{{ $item['label'] }}"
                            @if ($isActive) aria-current="page" @endif
                            class="sb-item relative my-0.5 flex items-center rounded-lg px-3 py-2 transition
                                {{ $isActive ? 'sb-active bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
                            @if ($isActive)
                                <span class="absolute bottom-1.5 left-0 top-1.5 w-[3px] rounded-r bg-blue-500"></span>
                            @endif
                            <i class="bi {{ $item['icon'] }} mr-3 w-4 text-center text-[15px]"></i>
                            <span class="sb-label flex-1 truncate">{{ $item['label'] }}</span>
                            @if ($badge)
                                <span class="sb-badge ml-2 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white">
                                    {{ $badge }}
                                </span>
                            @endif
                        </a>
                    @endforeach
                @endforeach
            </nav>

            <div class="shrink-0 space-y-2 border-t border-white/10 p-3">
                <button type="button" id="darkToggle" aria-pressed="false"
                    class="sb-item flex w-full items-center justify-center gap-2 rounded-lg bg-white/10 px-3 py-2 text-[13px] font-medium text-slate-200 hover:bg-white/15">
                    <i class="bi bi-moon" id="darkIcon"></i>
                    <span class="sb-label" id="darkLabel">Dark Mode</span>
                </button>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                        class="sb-item flex w-full items-center justify-center gap-2 rounded-lg border border-red-900/60 bg-red-950/70 px-3 py-2 text-[13px] font-medium text-slate-200 hover:bg-red-900">
                        <i class="bi bi-box-arrow-right"></i>
                        <span class="sb-label">Logout</span>
                    </button>
                </form>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">

            <header class="print:hidden sticky top-0 z-30 flex h-16 items-center justify-between border-b border-slate-200 bg-white/95 px-4 shadow-sm backdrop-blur sm:px-6">
                <div class="flex min-w-0 items-center gap-3">
                    <button type="button" id="mobileMenuBtn" aria-label="Open navigation" aria-controls="sidebar"
                        aria-expanded="false"
                        class="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 md:hidden">
                        <i class="bi bi-list text-lg"></i>
                    </button>
                    <div class="min-w-0">
                        <a href="{{ route('dashboard') }}" class="block" title="Go to dashboard">
                            <p class="truncate text-sm font-bold tracking-tight text-slate-800 sm:text-base">
                                {{ $schoolName }}</p>
                        </a>
                    </div>
                </div>

                <div class="flex items-center gap-2 sm:gap-3">
                    <span
                        class="hidden rounded-full bg-slate-100 px-3 py-1 text-[11px] font-bold text-slate-600 ring-1 ring-slate-200 lg:inline-flex">
                        AY {{ current_academic_year() }}
                    </span>
                    <button type="button" onclick="location.reload()" title="Refresh page" aria-label="Refresh page"
                        class="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50">
                        <i class="bi bi-arrow-repeat"></i>
                    </button>
                    <a href="{{ route('profile.edit') }}" title="My account"
                        class="flex items-center gap-2 rounded-full border border-slate-200 bg-white py-1 pl-1 pr-2.5 shadow-sm transition hover:bg-slate-50 sm:pr-3">
                        @if ($avatar)
                            <img src="{{ $avatar }}" alt="" class="h-7 w-7 rounded-full object-cover">
                        @else
                            <span
                                class="flex h-7 w-7 items-center justify-center rounded-full bg-[#0b1a4a] text-white">
                                <i class="bi bi-person-fill text-xs"></i>
                            </span>
                        @endif
                        <span class="hidden max-w-[9rem] truncate text-xs font-semibold text-slate-700 sm:inline">
                            {{ $user->name }}</span>
                    </a>
                </div>
            </header>

            <main id="mainContent" class="flex-1 p-4 sm:p-6 lg:p-8 print:p-0">
                <div class="mx-auto w-full max-w-7xl">
                    <x-ui.flash />
                    @yield('content')
                </div>
            </main>

            <footer class="print:hidden border-t border-slate-200 bg-white px-4 py-4 text-center text-xs text-slate-500 sm:px-6">
                <p>
                    &copy; {{ date('Y') }} {{ $schoolName }}. All rights reserved.
                    <span class="mx-1 text-slate-300">|</span>
                    Developed by <span class="font-semibold text-slate-700">Saurav Acharya</span>
                    <span class="mx-1 text-slate-300">|</span>
                    @can('settings.manage')
                    <a href="{{ route('admin.about') }}" class="ui-link">About App</a>
                    @endcan
                </p>
            </footer>
        </div>
    </div>

    <script>
        (function () {
            var sidebar = document.getElementById('sidebar');
            var backdrop = document.getElementById('sidebarBackdrop');
            var mobileBtn = document.getElementById('mobileMenuBtn');
            var closeBtn = document.getElementById('sidebarClose');
            var collapseBtn = document.getElementById('collapseBtn');
            var root = document.documentElement;
            var mobileQuery = window.matchMedia('(max-width: 767px)');
            var lastFocusedElement = null;
            var focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

            function sidebarIsOpen() {
                return mobileQuery.matches && !sidebar.classList.contains('-translate-x-full');
            }

            function updateCollapseButton() {
                var isCollapsed = sidebar.classList.contains('collapsed');
                var label = isCollapsed ? 'Expand sidebar' : 'Collapse sidebar';

                collapseBtn.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
                collapseBtn.setAttribute('aria-label', label);
                collapseBtn.setAttribute('title', label);
            }

            function openSidebar() {
                if (!mobileQuery.matches) {
                    return;
                }

                lastFocusedElement = document.activeElement;
                sidebar.classList.remove('-translate-x-full');
                backdrop.classList.remove('hidden');
                mobileBtn.setAttribute('aria-expanded', 'true');
                sidebar.setAttribute('role', 'dialog');
                sidebar.setAttribute('aria-modal', 'true');
                document.body.classList.add('overflow-hidden');

                window.setTimeout(function () {
                    closeBtn.focus();
                }, 0);
            }

            function closeSidebar(restoreFocus) {
                var wasOpen = sidebarIsOpen();

                sidebar.classList.add('-translate-x-full');
                backdrop.classList.add('hidden');
                mobileBtn.setAttribute('aria-expanded', 'false');
                sidebar.removeAttribute('role');
                sidebar.removeAttribute('aria-modal');
                document.body.classList.remove('overflow-hidden');

                if (restoreFocus && wasOpen && lastFocusedElement instanceof HTMLElement) {
                    lastFocusedElement.focus();
                }
            }

            if (localStorage.getItem('sidebarCollapsed') === '1') {
                sidebar.classList.add('collapsed');
            }
            updateCollapseButton();

            collapseBtn.addEventListener('click', function () {
                sidebar.classList.toggle('collapsed');
                localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed') ? '1' : '0');
                updateCollapseButton();
            });

            mobileBtn.addEventListener('click', openSidebar);
            closeBtn.addEventListener('click', function () {
                closeSidebar(true);
            });
            backdrop.addEventListener('click', function () {
                closeSidebar(true);
            });

            sidebar.querySelectorAll('a[href]').forEach(function (link) {
                link.addEventListener('click', function () {
                    if (mobileQuery.matches) {
                        closeSidebar();
                    }
                });
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    if (sidebarIsOpen()) {
                        event.preventDefault();
                        closeSidebar(true);
                    }

                    return;
                }

                if (event.key !== 'Tab' || !sidebarIsOpen()) {
                    return;
                }

                var focusable = Array.prototype.slice.call(sidebar.querySelectorAll(focusableSelector))
                    .filter(function (element) {
                        return !element.hasAttribute('hidden') && element.getClientRects().length > 0;
                    });

                if (!focusable.length) {
                    return;
                }

                var first = focusable[0];
                var last = focusable[focusable.length - 1];

                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            });

            mobileQuery.addEventListener('change', function (event) {
                if (!event.matches) {
                    closeSidebar();
                }
            });

            function applyDark(on) {
                root.classList.toggle('dark', on);
                document.getElementById('darkIcon').className = 'bi ' + (on ? 'bi-sun' : 'bi-moon');
                document.getElementById('darkLabel').textContent = on ? 'Light Mode' : 'Dark Mode';
                document.getElementById('darkToggle').setAttribute('aria-pressed', on ? 'true' : 'false');
            }

            applyDark(localStorage.getItem('darkMode') === '1');

            document.getElementById('darkToggle').addEventListener('click', function () {
                var on = !root.classList.contains('dark');
                localStorage.setItem('darkMode', on ? '1' : '0');
                applyDark(on);
            });

            var active = document.querySelector('#sidebarNav .sb-active');
            if (active) {
                active.scrollIntoView({ block: 'center' });
            }
        })();
    </script>

    @stack('scripts')
</body>

</html>
