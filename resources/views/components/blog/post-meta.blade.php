@props(['post'])

<p {{ $attributes->class('flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-slate-600') }}>
    <time datetime="{{ $post->published_at?->toDateString() }}">{{ $post->published_at?->format('M j, Y') }}</time>
    <span aria-hidden="true">·</span>
    <span>{{ $post->readingTimeMinutes() }} min read</span>
</p>
