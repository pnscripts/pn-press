@php($footerMenu = app(\App\Press\Menus::class)->tree('footer'))
<footer class="mt-16 border-t border-rule bg-surface">
    <div class="press-container grid gap-8 py-12 sm:grid-cols-[2fr_1fr]">
        <div>
            <p class="font-display text-xl font-semibold">{{ $siteName }}</p>
            @if ($tagline !== '')
                <p class="mt-2 max-w-md text-sm text-muted">{{ $tagline }}</p>
            @endif
            @if ($footerText = press_setting('site.footer_text'))
                <p class="mt-4 max-w-md text-sm text-muted">{{ $footerText }}</p>
            @endif
        </div>
        @if ($footerMenu)
            <nav aria-label="Footer">
                <ul class="space-y-2 text-sm">
                    @foreach ($footerMenu as $item)
                        <li><a href="{{ $item['url'] }}" class="text-muted underline-offset-4 hover:text-ink hover:underline" @if ($item['new_tab']) target="_blank" rel="noopener" @endif>{{ $item['label'] }}</a></li>
                    @endforeach
                </ul>
            </nav>
        @endif
    </div>
    @hook('footer')
    <div class="border-t border-rule">
        <div class="press-container flex flex-col gap-1 py-5 text-xs text-muted sm:flex-row sm:justify-between">
            <p>&copy; {{ now()->year }} {{ $siteName }}</p>
            <p>Published with <a href="https://pnscripts.com/products/pn-press" class="font-medium text-accent hover:underline">PN Scripts Press</a></p>
        </div>
    </div>
</footer>
