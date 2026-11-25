<header class="sticky top-0 z-40 border-b border-slate-200 bg-white/90 backdrop-blur">
    <div class="mx-auto flex h-16 w-full max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
        <a href="{{ route('home') }}" class="flex items-center gap-2.5" aria-label="{{ config('app.name', 'Pragyan Pathashala') }} home">
            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#0b1a4a] text-white">
                <i class="bi bi-buildings"></i>
            </span>
            <span class="max-w-[12rem] truncate text-sm font-extrabold text-slate-900 sm:max-w-none sm:text-base">
                {{ config('app.name', 'Pragyan Pathashala') }}
            </span>
        </a>

        <nav class="hidden items-center gap-6 text-sm font-semibold text-slate-600 md:flex" aria-label="Main navigation">
            <a href="{{ route('home') }}" class="hover:text-[#0b1a4a] {{ request()->routeIs('home') ? 'text-[#0b1a4a]' : '' }}">Home</a>
            <a href="{{ route('public.notices') }}" class="hover:text-[#0b1a4a] {{ request()->routeIs('public.notices') ? 'text-[#0b1a4a]' : '' }}">Notice Board</a>
            <a href="{{ route('public.team') }}" class="hover:text-[#0b1a4a] {{ request()->routeIs('public.team') ? 'text-[#0b1a4a]' : '' }}">Our Team</a>
        </nav>

        <div class="flex items-center gap-2">
            @auth
                <a href="{{ route('dashboard') }}" class="ui-btn ui-btn-primary ui-btn-sm">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            @else
                @if (Route::has('login'))
                    <a href="{{ route('login') }}" class="ui-btn ui-btn-secondary ui-btn-sm">Log in</a>
                @endif
                @if (Route::has('register'))
                    <a href="{{ route('register') }}" class="ui-btn ui-btn-primary ui-btn-sm">Register</a>
                @endif
            @endauth
        </div>
    </div>
</header>
