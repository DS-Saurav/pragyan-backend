<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="theme-color" content="#19518C">
    <title>@yield('title') | Pragyan Pathashala</title>
    <style>
        :root {
            color-scheme: light;
            --pp-blue: #19518c;
            --pp-blue-deep: #103d6b;
            --pp-blue-soft: #eaf4fb;
            --pp-ink: #183247;
            --pp-muted: #52687a;
            --pp-border: #cadbe8;
            --pp-surface: #ffffff;
            --pp-warm: #f9f7f2;
        }

        * { box-sizing: border-box; }

        html { background: var(--pp-warm); }

        body {
            min-width: 0;
            margin: 0;
            background:
                radial-gradient(circle at 15% 0%, rgba(69, 143, 196, .16), transparent 32rem),
                var(--pp-warm);
            color: var(--pp-ink);
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            line-height: 1.55;
        }

        html[lang="ne"] body,
        html[lang="ne"] h1 {
            font-family: "Nirmala UI", "Noto Sans Devanagari", Mangal, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
        }

        html[lang="ne"] h1 { font-weight: 800; }

        a { color: inherit; }

        .skip-link {
            position: fixed;
            z-index: 10;
            top: .75rem;
            left: .75rem;
            padding: .6rem .85rem;
            border-radius: .5rem;
            background: #fff;
            color: var(--pp-blue-deep);
            font-weight: 700;
            transform: translateY(-160%);
        }

        .skip-link:focus { transform: translateY(0); }

        .brand {
            display: flex;
            align-items: center;
            gap: .7rem;
            width: min(100% - 2rem, 68rem);
            margin: 0 auto;
            padding: 1.25rem 0;
            color: var(--pp-blue-deep);
            font-weight: 800;
            letter-spacing: -.015em;
            text-decoration: none;
        }

        .brand-mark {
            display: grid;
            width: 2.25rem;
            height: 2.25rem;
            place-items: center;
            border-radius: .7rem;
            background: var(--pp-blue);
            color: #fff;
            font-size: .78rem;
            letter-spacing: .04em;
        }

        main {
            display: grid;
            min-height: calc(100vh - 8rem);
            padding: 2rem 1rem 4rem;
            place-items: center;
        }

        .card {
            width: min(100%, 42rem);
            padding: clamp(1.5rem, 7vw, 3.5rem);
            overflow-wrap: anywhere;
            border: 1px solid var(--pp-border);
            border-radius: 1.25rem;
            background: var(--pp-surface);
            box-shadow: 0 1.25rem 3.5rem rgba(20, 57, 85, .12);
        }

        .status {
            margin: 0 0 .65rem;
            color: var(--pp-blue);
            font-size: .78rem;
            font-weight: 800;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        h1 {
            max-width: 18ch;
            margin: 0;
            color: var(--pp-blue-deep);
            font-family: ui-serif, Georgia, Cambria, "Times New Roman", serif;
            font-size: clamp(2rem, 8vw, 3.5rem);
            line-height: 1.08;
        }

        .message {
            max-width: 58ch;
            margin: 1.25rem 0 0;
            color: var(--pp-muted);
            font-size: clamp(1rem, 2.5vw, 1.125rem);
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: .75rem;
            margin-top: 2rem;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 2.75rem;
            padding: .7rem 1rem;
            border: 1px solid var(--pp-blue);
            border-radius: .65rem;
            background: var(--pp-blue);
            color: #fff;
            font-weight: 750;
            text-align: center;
            text-decoration: none;
        }

        .button:hover { background: var(--pp-blue-deep); }

        .button-secondary {
            background: #fff;
            color: var(--pp-blue-deep);
        }

        :focus-visible {
            outline: 3px solid #e09130;
            outline-offset: 3px;
        }

        footer {
            width: min(100% - 2rem, 68rem);
            margin: 0 auto;
            padding: 0 0 1.5rem;
            color: var(--pp-muted);
            font-size: .875rem;
            text-align: center;
        }

        @media (max-width: 24rem) {
            .brand { width: min(100% - 1.5rem, 68rem); }
            main { padding-inline: .75rem; }
            .card { border-radius: 1rem; }
            .button { width: 100%; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                scroll-behavior: auto !important;
                transition-duration: .01ms !important;
                animation-duration: .01ms !important;
                animation-iteration-count: 1 !important;
            }
        }
    </style>
</head>
<body>
    <a class="skip-link" href="#main-content">{{ __('public.skip_to_message') }}</a>

    <header>
        <a class="brand" href="{{ \App\Support\PublicLocaleUrl::route('home', [], null, false) }}"
            aria-label="{{ __('public.home_label', ['name' => config('app.name', 'Pragyan Pathashala')]) }}">
            <span class="brand-mark" aria-hidden="true">PP</span>
            <span>{{ config('app.name', 'Pragyan Pathashala') }}</span>
        </a>
    </header>

    <main id="main-content" tabindex="-1">
        <section class="card" aria-labelledby="error-heading">
            <p class="status">@yield('status')</p>
            <h1 id="error-heading">@yield('heading')</h1>
            <p class="message">@yield('message')</p>
            <div class="actions">
                <a class="button" href="{{ \App\Support\PublicLocaleUrl::route('home', [], null, false) }}">{{ __('public.return_home') }}</a>
                @hasSection('secondary-action')
                    @yield('secondary-action')
                @endif
            </div>
        </section>
    </main>

    <footer>{{ config('app.name', 'Pragyan Pathashala') }}</footer>
</body>
</html>
