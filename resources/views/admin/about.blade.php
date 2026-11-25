@extends('layouts.admin')

@section('title', 'About App')

@section('content')
    <div class="ui-page">
        <x-ui.page-header title="About App"
            subtitle="Application information, developer credit and the technology behind your school management system." />

        <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-[#0b1a4a] via-[#0d235f] to-slate-900 text-white shadow-lg">
            <div class="grid gap-8 p-6 sm:p-8 lg:grid-cols-[1.4fr_1fr] lg:items-center">
                <div>
                    <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-[11px] font-bold uppercase tracking-wide text-emerald-300">
                        <i class="bi bi-buildings"></i> School Management System
                    </span>
                    <h2 class="mt-4 text-2xl font-extrabold tracking-tight sm:text-3xl">
                        {{ $settings['school_name'] ?? config('app.name', 'Pragyan Pathashala') }}
                    </h2>
                    <p class="mt-2 max-w-xl text-sm text-slate-300">
                        {{ $settings['tagline'] ?? 'Learn. Grow. Succeed.' }}
                    </p>
                    <p class="mt-4 max-w-xl text-sm leading-relaxed text-slate-300">
                        A single workspace for admissions, students, classes, attendance, exams, finance,
                        discipline, communication and school administration.
                    </p>
                    <div class="mt-6 flex flex-wrap gap-2 text-[11px] font-semibold text-slate-200">
                        <span class="rounded-full bg-white/10 px-3 py-1">Academic year {{ current_academic_year() }}</span>
                        <span class="rounded-full bg-white/10 px-3 py-1">Laravel {{ app()->version() }}</span>
                        <span class="rounded-full bg-white/10 px-3 py-1">{{ ucfirst(app()->environment()) }}</span>
                    </div>
                </div>

                <div class="rounded-xl border border-white/15 bg-white/10 p-5 backdrop-blur">
                    <p class="text-[11px] font-bold uppercase tracking-widest text-emerald-300">Developed by</p>
                    <p class="mt-2 text-xl font-extrabold text-white">Saurav Acharya</p>
                    <p class="mt-1 text-sm text-slate-300">Design, development and ongoing maintenance.</p>
                    <div class="mt-4 flex items-center gap-2 border-t border-white/10 pt-4 text-xs text-slate-300">
                        <i class="bi bi-code-slash text-emerald-300"></i>
                        <span>Built for the school community.</span>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-5 lg:grid-cols-2">
            <section class="ui-card">
                <div class="ui-card-header">
                    <h3 class="text-sm font-bold text-slate-900">School profile</h3>
                    <i class="bi bi-building text-slate-400"></i>
                </div>
                <dl class="divide-y divide-slate-100">
                    <div class="flex gap-4 px-5 py-3 text-sm">
                        <dt class="w-24 shrink-0 font-semibold text-slate-500">Name</dt>
                        <dd class="text-slate-800">{{ $settings['school_name'] ?? config('app.name', 'Pragyan Pathashala') }}</dd>
                    </div>
                    <div class="flex gap-4 px-5 py-3 text-sm">
                        <dt class="w-24 shrink-0 font-semibold text-slate-500">Address</dt>
                        <dd class="text-slate-800">{{ $settings['address'] ?? 'Not set' }}</dd>
                    </div>
                    <div class="flex gap-4 px-5 py-3 text-sm">
                        <dt class="w-24 shrink-0 font-semibold text-slate-500">Phone</dt>
                        <dd class="text-slate-800">{{ $settings['phone'] ?? 'Not set' }}</dd>
                    </div>
                    <div class="flex gap-4 px-5 py-3 text-sm">
                        <dt class="w-24 shrink-0 font-semibold text-slate-500">Email</dt>
                        <dd class="min-w-0 break-words text-slate-800">{{ $settings['email'] ?? 'Not set' }}</dd>
                    </div>
                    <div class="flex gap-4 px-5 py-3 text-sm">
                        <dt class="w-24 shrink-0 font-semibold text-slate-500">Website</dt>
                        <dd class="min-w-0 break-words text-slate-800">
                            @if (! empty($settings['website']))
                                <a href="{{ $settings['website'] }}" target="_blank" rel="noopener" class="ui-link">{{ $settings['website'] }}</a>
                            @else
                                Not set
                            @endif
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="ui-card">
                <div class="ui-card-header">
                    <h3 class="text-sm font-bold text-slate-900">System information</h3>
                    <i class="bi bi-cpu text-slate-400"></i>
                </div>
                <dl class="divide-y divide-slate-100">
                    <div class="flex gap-4 px-5 py-3 text-sm">
                        <dt class="w-32 shrink-0 font-semibold text-slate-500">Application</dt>
                        <dd class="text-slate-800">{{ config('app.name', 'Pragyan Pathashala') }}</dd>
                    </div>
                    <div class="flex gap-4 px-5 py-3 text-sm">
                        <dt class="w-32 shrink-0 font-semibold text-slate-500">Laravel</dt>
                        <dd class="text-slate-800">{{ app()->version() }}</dd>
                    </div>
                    <div class="flex gap-4 px-5 py-3 text-sm">
                        <dt class="w-32 shrink-0 font-semibold text-slate-500">PHP</dt>
                        <dd class="text-slate-800">{{ PHP_VERSION }}</dd>
                    </div>
                    <div class="flex gap-4 px-5 py-3 text-sm">
                        <dt class="w-32 shrink-0 font-semibold text-slate-500">Environment</dt>
                        <dd class="text-slate-800">{{ ucfirst(app()->environment()) }}</dd>
                    </div>
                    <div class="flex gap-4 px-5 py-3 text-sm">
                        <dt class="w-32 shrink-0 font-semibold text-slate-500">Timezone</dt>
                        <dd class="text-slate-800">{{ config('app.timezone') }}</dd>
                    </div>
                </dl>
            </section>
        </div>

        <section class="ui-card">
            <div class="ui-card-header">
                <h3 class="text-sm font-bold text-slate-900">Technology</h3>
                <i class="bi bi-stack text-slate-400"></i>
            </div>
            <div class="flex flex-wrap gap-2 p-5">
                @foreach (['Laravel', 'PHP', 'Blade', 'Tailwind CSS', 'Alpine.js', 'Vite', 'Bootstrap Icons', 'MySQL / MariaDB'] as $technology)
                    <x-ui.badge tone="neutral">{{ $technology }}</x-ui.badge>
                @endforeach
            </div>
        </section>

        <section class="ui-card">
            <div class="ui-card-header">
                <h3 class="text-sm font-bold text-slate-900">Quick links</h3>
                <i class="bi bi-link-45deg text-slate-400"></i>
            </div>
            <div class="flex flex-wrap gap-2 p-5">
                <a href="{{ route('dashboard') }}" class="ui-btn ui-btn-secondary ui-btn-sm">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="{{ route('public.notices') }}" class="ui-btn ui-btn-secondary ui-btn-sm">
                    <i class="bi bi-newspaper"></i> Notice Board
                </a>
                <a href="{{ route('public.team') }}" class="ui-btn ui-btn-secondary ui-btn-sm">
                    <i class="bi bi-people"></i> Our Team
                </a>
                <a href="{{ route('profile.edit') }}" class="ui-btn ui-btn-secondary ui-btn-sm">
                    <i class="bi bi-person-circle"></i> My Account
                </a>
                @can('settings.manage')
                    <a href="{{ route('admin.settings.index') }}" class="ui-btn ui-btn-primary ui-btn-sm">
                        <i class="bi bi-gear"></i> School Settings
                    </a>
                @endcan
            </div>
        </section>

        <p class="text-center text-xs text-slate-400">
            &copy; {{ date('Y') }} {{ $settings['school_name'] ?? config('app.name', 'Pragyan Pathashala') }}.
            Developed by <span class="font-semibold text-slate-600">Saurav Acharya</span>.
        </p>
    </div>
@endsection
