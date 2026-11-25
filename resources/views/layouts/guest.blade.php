<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0b1a4a">

    <title>{{ config('app.name', 'Pragyan Pathashala') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased text-slate-900">
    <div class="grid min-h-screen lg:grid-cols-2">
        <aside class="hidden flex-col justify-between bg-gradient-to-br from-[#0b1a4a] via-[#0d235f] to-slate-900 p-10 text-white lg:flex">
            <a href="{{ route('home') }}" class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/10">
                    <i class="bi bi-buildings text-lg"></i>
                </span>
                <span class="text-lg font-extrabold">{{ config('app.name', 'Pragyan Pathashala') }}</span>
            </a>

            <div>
                <p class="text-[11px] font-bold uppercase tracking-widest text-emerald-300">School Management System</p>
                <h1 class="mt-4 max-w-md text-3xl font-extrabold leading-tight">
                    One place for your school's daily work.
                </h1>
                <p class="mt-4 max-w-md text-sm leading-relaxed text-slate-300">
                    Admissions, students, attendance, exams, finance, communication and support — organised and
                    accessible to the right people.
                </p>
                <ul class="mt-8 space-y-3 text-sm text-slate-200">
                    <li class="flex items-center gap-3">
                        <i class="bi bi-check-circle-fill text-emerald-400"></i> Role-based access
                    </li>
                    <li class="flex items-center gap-3">
                        <i class="bi bi-check-circle-fill text-emerald-400"></i> Clear, responsive workspace
                    </li>
                    <li class="flex items-center gap-3">
                        <i class="bi bi-check-circle-fill text-emerald-400"></i> Works on desktop and mobile
                    </li>
                </ul>
            </div>

            <p class="text-xs text-slate-400">
                &copy; {{ date('Y') }} {{ config('app.name', 'Pragyan Pathashala') }}. Developed by Saurav Acharya.
            </p>
        </aside>

        <main class="flex flex-col justify-center px-4 py-10 sm:px-6 lg:px-12">
            <div class="mx-auto w-full max-w-md">
                <a href="{{ route('home') }}" class="mb-8 flex items-center gap-3 lg:hidden">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#0b1a4a] text-white">
                        <i class="bi bi-buildings text-lg"></i>
                    </span>
                    <span class="text-lg font-extrabold text-slate-900">{{ config('app.name', 'Pragyan Pathashala') }}</span>
                </a>

                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                    {{ $slot }}
                </div>

                <p class="mt-6 text-center text-xs text-slate-500">
                    <a href="{{ route('home') }}" class="ui-link">Home</a>
                    <span class="mx-2 text-slate-300">|</span>
                    <a href="{{ route('public.notices') }}" class="ui-link">Notice Board</a>
                    <span class="mx-2 text-slate-300">|</span>
                    <a href="{{ route('public.team') }}" class="ui-link">Our Team</a>
                </p>
            </div>
        </main>
    </div>
</body>

</html>
