@props(['categories', 'active' => null])

@if ($categories->isNotEmpty())
    <nav aria-label="Categories" {{ $attributes->class('flex flex-wrap gap-2') }}>
        <a href="{{ route('home') }}" @class([
            'rounded-full px-3.5 py-1.5 text-sm font-medium ring-1 transition',
            'bg-brand-500 text-navy-950 ring-brand-500' => $active === null,
            'text-slate-200 ring-white/20 hover:bg-white/10 hover:text-white' => $active !== null,
        ]) @if ($active === null) aria-current="page" @endif>All posts</a>
        @foreach ($categories as $category)
            <a href="{{ route('categories.show', $category->slug) }}" @class([
                'rounded-full px-3.5 py-1.5 text-sm font-medium ring-1 transition',
                'bg-brand-500 text-navy-950 ring-brand-500' => $active === $category->slug,
                'text-slate-200 ring-white/20 hover:bg-white/10 hover:text-white' => $active !== $category->slug,
            ]) @if ($active === $category->slug) aria-current="page" @endif>
                {{ $category->name }} <span class="opacity-70">{{ $category->posts_count }}</span>
            </a>
        @endforeach
    </nav>
@endif
