@php($primary = app(\App\Press\Menus::class)->tree('primary'))
<header class="border-b border-rule bg-paper">
    <div class="h-1 bg-accent"></div>
    <div class="press-container flex flex-wrap items-center justify-between gap-x-8 gap-y-3 py-5">
        <a href="{{ press_locale_url('/') }}" class="group flex items-center gap-3">
            <x-press.mark :size="36" class="shrink-0 transition group-hover:rotate-[-8deg]" />
            <span class="flex flex-col">
                <span class="font-display text-2xl font-semibold leading-none tracking-tight">{{ $siteName }}</span>
                @if ($tagline !== '')
                    <span class="mt-1 text-xs text-muted">{{ $tagline }}</span>
                @endif
            </span>
        </a>

        <nav aria-label="Main" data-press-nav class="-mx-1 flex max-w-full flex-wrap items-center gap-1 text-sm font-medium">
            @forelse ($primary as $item)
                @include('press.partials.nav-item', ['item' => $item])
            @empty
                <a href="{{ press_route('blog') }}" @class(['rounded-full px-3 py-2 hover:bg-rule/60', 'bg-ink text-paper hover:bg-ink' => request()->routeIs('blog', 'home', '*.blog', '*.home')])>{{ __('Blog') }}</a>
            @endforelse
            @include('press.partials.language-switcher')
        </nav>
    </div>
</header>
