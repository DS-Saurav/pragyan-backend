<footer class="border-t border-slate-200 bg-white">
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-4 px-4 py-8 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
        <p>
            &copy; {{ date('Y') }} {{ config('app.name', 'Pragyan Pathashala') }}. All rights reserved.
        </p>
        <nav aria-label="Footer navigation">
            <ul class="flex flex-wrap items-center gap-3">
                <li><a href="{{ route('home') }}" class="ui-link">Home</a></li>
                <li class="text-slate-300">|</li>
                <li><a href="{{ route('public.notices') }}" class="ui-link">Notices</a></li>
                <li class="text-slate-300">|</li>
                <li><a href="{{ route('public.team') }}" class="ui-link">Team</a></li>
            </ul>
        </nav>
        <p>
            Developed by <span class="font-semibold text-slate-700">Saurav Acharya</span>
        </p>
    </div>
</footer>
