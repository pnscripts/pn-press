@props(['post'])

<p {{ $attributes->class('flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted') }}>
    <time datetime="{{ $post->published_at?->toDateString() }}" class="font-medium text-ink">{{ $post->published_at?->format('j M Y') }}</time>
    <span>{{ $post->readingTimeMinutes() }} min read</span>
</p>
