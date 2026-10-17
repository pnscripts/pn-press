{{-- Nocturne overrides the core header: a centred masthead with the menu underneath. --}}
@php($primary = app(\App\Press\Menus::class)->tree('primary'))
@php($showTagline = app(\App\Press\Themes\ThemeManager::class)->setting('show_tagline', true))
<header class="border-b border-rule bg-paper">
    <div class="press-container flex flex-col items-center gap-4 py-8 text-center">
        <a href="{{ press_locale_url('/') }}" class="flex flex-col items-center gap-3">
            <x-press.mark :size="44" />
            <span class="font-display text-3xl font-semibold tracking-tight">{{ $siteName }}</span>
        </a>
        @if ($showTagline && $tagline !== '')
            <p class="text-sm text-muted">{{ $tagline }}</p>
        @endif
        <nav aria-label="Main" data-press-nav class="flex max-w-full flex-wrap justify-center gap-1 text-sm font-medium">
            @forelse ($primary as $item)
                @include('press.partials.nav-item', ['item' => $item])
            @empty
                <a href="{{ press_route('blog') }}" class="rounded-full px-3 py-2 hover:bg-rule">{{ __('Blog') }}</a>
            @endforelse
            @include('press.partials.language-switcher')
        </nav>
    </div>
    <div class="h-px bg-gradient-to-r from-transparent via-accent to-transparent"></div>
</header>
