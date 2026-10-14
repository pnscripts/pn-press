@props(['categories', 'active' => null])

@if ($categories->isNotEmpty())
    <nav aria-label="Categories" {{ $attributes->class('flex flex-wrap gap-x-4 gap-y-2 text-sm') }}>
        <a href="{{ route('blog') }}" @class([
            'border-b-2 pb-0.5 font-medium transition',
            'border-accent text-ink' => $active === null,
            'border-transparent text-muted hover:text-ink' => $active !== null,
        ]) @if ($active === null) aria-current="page" @endif>All</a>
        @foreach ($categories as $category)
            <a href="{{ route('categories.show', $category->slug) }}" @class([
                'border-b-2 pb-0.5 font-medium transition',
                'border-accent text-ink' => $active === $category->slug,
                'border-transparent text-muted hover:text-ink' => $active !== $category->slug,
            ]) @if ($active === $category->slug) aria-current="page" @endif>
                {{ $category->name }} <span class="text-xs opacity-60">{{ $category->posts_count }}</span>
            </a>
        @endforeach
    </nav>
@endif
