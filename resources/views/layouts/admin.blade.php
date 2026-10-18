<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Dashboard') | {{ config('app.name', 'Pragyan Pathashala') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

    <!-- Line icons (Bootstrap Icons) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        /* ---- Sidebar scrollbar ---- */
        #sidebarNav::-webkit-scrollbar {
            width: 4px;
        }

        #sidebarNav::-webkit-scrollbar-thumb {
            background: rgba(148, 163, 184, .35);
            border-radius: 4px;
        }

        /* ---- Collapsed (icon-only) sidebar ---- */
        #sidebar {
            transition: width .2s ease;
        }

        #sidebar.collapsed {
            width: 4.5rem;
        }

        #sidebar.collapsed .sb-label,
        #sidebar.collapsed .sb-section,
        #sidebar.collapsed .sb-profile-text,
        #sidebar.collapsed .sb-badge {
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

        #sidebar.collapsed .sb-brand-name {
            display: none;
        }

        #sidebar.collapsed .sb-collapse i {
            transform: rotate(180deg);
        }

        /* ---- Quick dark mode (inverts the page area) ---- */
        html.dark main {
            filter: invert(1) hue-rotate(180deg);
            background: #fff;
        }

        html.dark main img,
        html.dark main video {
            filter: invert(1) hue-rotate(180deg);
        }
    </style>
    @stack('head')
</head>

<body class="font-sans antialiased bg-slate-100">
    @php
        $schoolName = config('app.name', 'Pragyan Pathashala');
        $userRole = ucfirst(Auth::user()->role ?? 'admin');

        // Optional counters, e.g. View::share('sidebarBadges', ['discipline' => 2, 'helpdesk' => 2, 'complaints' => 1]);
        $badges = $sidebarBadges ?? [];

        /*
         | Menu definition: [label, icon, route name (null = not built yet), active pattern, badge key]
         | A route that does not exist yet automatically falls back to "#".
         */
        $menu = [
            'Overview' => [
                ['Dashboard', 'bi-speedometer2', 'dashboard', 'dashboard'],
                ['Reports', 'bi-graph-up-arrow', null, null],
            ],
            'Records' => [
                ['Admissions', 'bi-person-plus', 'admin.admissions.index', 'admin.admissions.*'],
                ['Students', 'bi-mortarboard', 'admin.students.index', 'admin.students.*'],
                ['Classes & Sections', 'bi-diagram-3', 'admin.classes.index', 'admin.classes.*'],
                ['Parents', 'bi-people', 'admin.parents.index', 'admin.parents.*'],
                ['Assets', 'bi-box', 'admin.assets.index', 'admin.assets.*'],
                ['Stock / Inventory', 'bi-boxes', 'admin.inventory.index', 'admin.inventory.*'],
                ['Subjects', 'bi-book', 'admin.subjects.index', 'admin.subjects.*'],
                ['Teachers', 'bi-person-workspace', 'admin.teachers.index', 'admin.teachers.*'],
                ['Substitutes', 'bi-arrow-repeat', null, null],
            ],
            'Library' => [
                ['Books Catalog', 'bi-collection', 'admin.library.books.index', 'admin.library.books.*'],
                [
                    'Issue / Return',
                    'bi-journal-bookmark',
                    'admin.library.circulation.index',
                    'admin.library.circulation.*',
                ],
            ],
            'Daily' => [
                ['Timetable', 'bi-calendar3', 'admin.timetable.index', 'admin.timetable.*'],
                ['Attendance', 'bi-person-check', 'admin.attendance.index', 'admin.attendance.*'],
                ['School Notices', 'bi-megaphone', 'admin.notices.index', 'admin.notices.*'],
                ['PTM', 'bi-person-lines-fill', 'admin.ptm.index', 'admin.ptm.*'],
                ['Calendar', 'bi-calendar-event', 'admin.calendar.index', 'admin.calendar.*'],
                ['Lesson Planning', 'bi-pencil-square', 'admin.lesson-plans.index', 'admin.lesson-plans.*'],
                ['Teaching Logbook', 'bi-journal-text', 'admin.teaching-logs.index', 'admin.teaching-logs.*'],
            ],
            'Academic' => [
                ['Exams', 'bi-clipboard-check', 'admin.exams.index', 'admin.exams.*'],
                ['Results / Marks', 'bi-award', 'admin.results.index', 'admin.results.*'],
                ['Hall Tickets', 'bi-ticket-detailed', 'admin.hall-tickets.index', 'admin.hall-tickets.*'],
                ['Discipline', 'bi-shield-check', 'admin.discipline.index', 'admin.discipline.*'],
                ['Conduct', 'bi-patch-check', null, null],
                ['Activities', 'bi-trophy', null, null],
            ],
            'Finance' => [
                ['Fee Structure', 'bi-receipt', null, null],
                ['Daily Accounts', 'bi-wallet2', null, null],
                ['Payroll / Salary', 'bi-cash-stack', null, null],
            ],
            'Support' => [
                ['Helpdesk', 'bi-headset', null, null, 'helpdesk'],
                ['Complaints', 'bi-chat-left-text', null, null, 'complaints'],
                ['Documents', 'bi-folder2-open', null, null],
            ],
            'Administration' => [['Users / Staff', 'bi-person-gear', null, null]],
            'Profile' => [
                ['My Account', 'bi-person-circle', 'profile.edit', 'profile.*'],
                ['Settings', 'bi-gear', null, null],
                ['About App', 'bi-info-circle', null, null],
            ],
        ];
    @endphp

    <div class="min-h-screen flex">

        <!-- ================= Sidebar ================= -->
        <aside id="sidebar"
            class="print:hidden w-64 shrink-0 hidden md:flex flex-col h-screen sticky top-0 text-slate-300 bg-gradient-to-b from-[#0b1a4a] via-[#070f2b] to-black">

            <!-- Brand row -->
            <div class="flex items-center justify-between px-4 h-16 border-b border-white/10 shrink-0">
                <div class="flex items-center gap-2.5 min-w-0">
                    <i class="bi bi-buildings text-white text-lg"></i>
                    <span class="sb-brand-name text-white font-bold text-[15px] truncate">{{ $schoolName }}</span>
                </div>
                <button type="button" id="collapseBtn" title="Collapse sidebar"
                    class="sb-collapse w-7 h-7 rounded-md bg-white/10 hover:bg-white/20 text-slate-200 flex items-center justify-center shrink-0">
                    <i class="bi bi-chevron-left text-xs transition"></i>
                </button>
            </div>

            <!-- Profile block -->
            <div class="flex flex-col items-center py-5 shrink-0">
                <div
                    class="sb-avatar w-[72px] h-[72px] rounded-full bg-white/10 border-2 border-white/25 flex items-center justify-center">
                    <i class="bi bi-mortarboard-fill text-white text-3xl"></i>
                </div>
                <div class="sb-profile-text text-center mt-3">
                    <p class="text-white font-bold text-sm">{{ Auth::user()->name }}</p>
                    <span
                        class="inline-block mt-1 px-3 py-0.5 rounded-full bg-white/10 text-[11px] text-slate-300">{{ $userRole }}</span>
                </div>
            </div>

            <!-- Navigation -->
            <nav id="sidebarNav" class="flex-1 overflow-y-auto px-3 pb-3 text-[13px] font-medium">
                @foreach ($menu as $section => $items)
                    <p
                        class="sb-section px-3 pt-4 pb-1.5 text-[10px] font-semibold uppercase tracking-widest text-slate-500">
                        {{ $section }}
                    </p>
                    @foreach ($items as $item)
                        @php
                            [$label, $icon, $routeName, $pattern] = $item;
                            $badgeKey = $item[4] ?? null;
                            $href = $routeName && Route::has($routeName) ? route($routeName) : '#';
                            $isActive = $pattern && request()->routeIs($pattern);
                        @endphp
                        <a href="{{ $href }}" title="{{ $label }}"
                            class="sb-item relative flex items-center px-3 py-2 my-0.5 rounded-lg transition
                                {{ $isActive ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
                            @if ($isActive)
                                <span
                                    class="absolute left-0 top-1.5 bottom-1.5 w-[3px] rounded-r bg-emerald-500"></span>
                            @endif
                            <i class="bi {{ $icon }} text-[15px] mr-3 w-4 text-center"></i>
                            <span class="sb-label flex-1 truncate">{{ $label }}</span>
                            @if ($badgeKey && !empty($badges[$badgeKey]))
                                <span
                                    class="sb-badge ml-2 min-w-[18px] h-[18px] px-1 rounded-full bg-red-600 text-white text-[10px] font-bold flex items-center justify-center">
                                    {{ $badges[$badgeKey] }}
                                </span>
                            @endif
                        </a>
                    @endforeach
                @endforeach
            </nav>

            <!-- Footer: dark mode + logout -->
            <div class="p-3 space-y-2 border-t border-white/10 shrink-0">
                <button type="button" id="darkToggle"
                    class="sb-item w-full flex items-center justify-center gap-2 px-3 py-2 rounded-lg bg-white/10 hover:bg-white/15 text-slate-200 text-[13px] font-medium">
                    <i class="bi bi-moon" id="darkIcon"></i>
                    <span class="sb-label" id="darkLabel">Dark Mode</span>
                </button>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                        class="sb-item w-full flex items-center justify-center gap-2 px-3 py-2 rounded-lg bg-red-950/70 hover:bg-red-900 border border-red-900/60 text-slate-200 text-[13px] font-medium">
                        <i class="bi bi-box-arrow-right"></i>
                        <span class="sb-label">Logout</span>
                    </button>
                </form>
            </div>
        </aside>

        <!-- ================= Main ================= -->
        <div class="flex-1 flex flex-col min-w-0">

            <!-- Top header -->
            <header
                class="print:hidden h-16 bg-white border-b border-gray-200 flex items-center justify-between px-4 sm:px-6 shadow-sm">
                <div class="flex items-center gap-3">
                    <button type="button" id="mobileMenuBtn" class="md:hidden text-gray-600 text-xl">
                        <i class="bi bi-list"></i>
                    </button>
                    <h2 class="text-base font-bold text-slate-800">@yield('title', 'Dashboard')</h2>
                </div>

                <div class="flex items-center gap-4 sm:gap-5">
                    <div class="relative w-64 hidden sm:block">
                        <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                        <input type="text" id="globalSearch" placeholder="Search everything..."
                            class="w-full bg-slate-100 border border-gray-200 rounded-lg pl-9 pr-14 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <kbd
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-[10px] font-mono bg-white border border-gray-200 rounded px-1.5 py-0.5 text-gray-500">Ctrl
                            K</kbd>
                    </div>
                    <div class="hidden lg:flex items-center gap-2 text-sm text-gray-600">
                        <i class="bi bi-building text-gray-400"></i>
                        <span>{{ $schoolName }}</span>
                    </div>
                    <span class="hidden sm:inline text-sm text-gray-600">Welcome, <b
                            class="text-slate-900">{{ Auth::user()->name }}</b></span>
                    <button type="button" onclick="location.reload()" title="Refresh"
                        class="w-9 h-9 rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 flex items-center justify-center">
                        <i class="bi bi-arrow-repeat"></i>
                    </button>
                </div>
            </header>

            <!-- Page content -->
            <main class="flex-1 p-6 overflow-y-auto print:p-0">
                @yield('content')
            </main>
        </div>
    </div>

    <script>
        (function() {
            var sidebar = document.getElementById('sidebar');
            var root = document.documentElement;

            // Collapse sidebar (remembered)
            if (localStorage.getItem('sidebarCollapsed') === '1') sidebar.classList.add('collapsed');
            document.getElementById('collapseBtn').addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed') ? '1' : '0');
            });

            // Mobile: show sidebar as overlay
            document.getElementById('mobileMenuBtn').addEventListener('click', function() {
                sidebar.classList.toggle('hidden');
                sidebar.classList.toggle('fixed');
                sidebar.classList.toggle('z-50');
                sidebar.classList.toggle('left-0');
                sidebar.classList.toggle('top-0');
            });

            // Dark mode (remembered)
            function applyDark(on) {
                root.classList.toggle('dark', on);
                document.getElementById('darkIcon').className = 'bi ' + (on ? 'bi-sun' : 'bi-moon');
                document.getElementById('darkLabel').textContent = on ? 'Light Mode' : 'Dark Mode';
            }
            applyDark(localStorage.getItem('darkMode') === '1');
            document.getElementById('darkToggle').addEventListener('click', function() {
                var on = !root.classList.contains('dark');
                localStorage.setItem('darkMode', on ? '1' : '0');
                applyDark(on);
            });

            // Ctrl+K focuses the search box
            document.addEventListener('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                    e.preventDefault();
                    var s = document.getElementById('globalSearch');
                    if (s) s.focus();
                }
            });

            // Keep the active menu item in view
            var active = document.querySelector('#sidebarNav .bg-white\\/10');
            if (active) active.scrollIntoView({
                block: 'center'
            });
        })();
    </script>

    @stack('scripts')
</body>

</html>
s
