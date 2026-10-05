<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @php($pageTitle = trim($__env->yieldContent('title')))
        <title>{{ $pageTitle !== '' && $pageTitle !== config('app.name') ? $pageTitle.' · '.config('app.name') : config('app.name') }}</title>
        <meta name="description" content="@yield('description', config('app.name').': articles, guides and notes.')">
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:title" content="{{ $pageTitle !== '' ? $pageTitle : config('app.name') }}">
        <meta property="og:description" content="@yield('description', config('app.name').': articles, guides and notes.')">
        @hasSection('og_image')
            <meta property="og:image" content="@yield('og_image')">
        @endif
        <meta name="theme-color" content="#0a1630">
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
        @endif
    </head>
    <body class="flex min-h-screen flex-col bg-slate-50 font-sans text-slate-900 antialiased">
        <a href="#content" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:text-slate-900">
            Skip to content
        </a>

        <header class="bg-navy-950 text-white">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5 text-lg font-semibold tracking-tight">
                    <svg class="size-8 shrink-0" viewBox="0 0 32 32" aria-hidden="true" focusable="false">
                        <rect width="32" height="32" rx="8" fill="#142a57"/>
                        <path d="M9 23V9h7.2a4.6 4.6 0 0 1 0 9.2H13" fill="none" stroke="#f67a3c" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="22.5" cy="23" r="2.2" fill="#bcd3ff"/>
                    </svg>
                    <span>{{ config('app.name') }}</span>
                </a>
                <nav aria-label="Main" class="flex items-center gap-1 text-sm font-medium">
                    <a href="{{ route('home') }}" @class(['rounded-md px-3 py-2 transition hover:bg-white/10 hover:text-white', 'text-white' => request()->routeIs('home'), 'text-slate-300' => ! request()->routeIs('home')]) @if (request()->routeIs('home')) aria-current="page" @endif>Blog</a>
                    <a href="{{ url('/admin') }}" class="rounded-md px-3 py-2 text-slate-300 transition hover:bg-white/10 hover:text-white">Admin</a>
                </nav>
            </div>
        </header>

        <main id="content" class="flex-1">
            @yield('content')
        </main>

        <footer class="border-t border-slate-200 bg-white">
            <div class="mx-auto flex max-w-6xl flex-col gap-2 px-4 py-8 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <p>&copy; {{ now()->year }} {{ config('app.name') }}</p>
                <p>Powered by <a href="https://pnscripts.com/products/pn-press" class="font-medium text-brand-700 underline-offset-4 hover:underline">PN Press</a></p>
            </div>
        </footer>
    </body>
</html>
