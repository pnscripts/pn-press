@php
    $press = app(\App\Press\Settings::class);
    $themes = app(\App\Press\Themes\ThemeManager::class);
    $siteName = $press->siteName();
    $tagline = (string) $press->localized('site.tagline', null, __('Stories, notes and pages'));
    $locales = app(\App\Press\I18n\Locales::class);
    $pageTitle = trim($__env->yieldContent('title'));
    $fullTitle = $pageTitle !== '' && $pageTitle !== $siteName ? $pageTitle.' · '.$siteName : $siteName;
    $description = trim($__env->yieldContent('description')) ?: $tagline;
    $robots = trim($__env->yieldContent('robots'));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" {{ $themes->htmlAttributes() }}>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $fullTitle }}</title>
        <meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags($description), 300) }}">
        <link rel="canonical" href="{{ trim($__env->yieldContent('canonical')) ?: url()->current() }}">
        @if ($locales->enabled() && count($alternates = $locales->alternates()) > 1)
            @foreach ($alternates as $code => $href)
                <link rel="alternate" hreflang="{{ $code }}" href="{{ $href }}">
            @endforeach
            @isset($alternates[$locales->default()])
                <link rel="alternate" hreflang="x-default" href="{{ $alternates[$locales->default()] }}">
            @endisset
        @endif
        @if ($robots !== '')
            <meta name="robots" content="{{ $robots }}">
        @endif
        <meta property="og:site_name" content="{{ $siteName }}">
        <meta property="og:title" content="{{ $pageTitle !== '' ? $pageTitle : $siteName }}">
        <meta property="og:description" content="{{ \Illuminate\Support\Str::limit(strip_tags($description), 300) }}">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:locale" content="{{ str_replace('-', '_', app()->getLocale()) }}">
        @hasSection('og_image')
            <meta property="og:image" content="@yield('og_image')">
            <meta name="twitter:card" content="summary_large_image">
        @endif
        <meta name="theme-color" content="#0f6e66">
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=fraunces:400,400i,600,700|inter:400,500,600" rel="stylesheet" />
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            @include('press.partials.cdn-fallback')
        @endif
        {{ $themes->styleTag() }}
        @stack('head')
        @hook('head')
    </head>
    <body class="flex min-h-screen flex-col bg-paper font-sans text-ink antialiased">
        @hook('body.start')
        <a href="#content" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-full focus:bg-surface focus:px-4 focus:py-2">
            {{ __('Skip to content') }}
        </a>

        @include('press.partials.header', ['siteName' => $siteName, 'tagline' => $tagline])

        <main id="content" class="flex-1">
            @yield('content')
        </main>

        @include('press.partials.footer', ['siteName' => $siteName, 'tagline' => $tagline])
        @include('press.partials.menu-script')
        @hook('body.end')
    </body>
</html>
