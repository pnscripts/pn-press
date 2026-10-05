@props(['post', 'featured' => false])

<article @class([
    'group relative flex flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:shadow-md',
    'md:flex-row' => $featured,
])>
    @if ($post->featuredImageUrl())
        <div @class(['shrink-0 overflow-hidden bg-navy-900', 'md:w-3/5' => $featured])>
            <img src="{{ $post->featuredImageUrl() }}" alt="" loading="lazy"
                 @class(['aspect-[1200/630] w-full object-cover transition duration-300 group-hover:scale-[1.02]', 'md:h-full' => $featured])>
        </div>
    @endif

    <div @class(['flex flex-1 flex-col p-5 sm:p-6', 'md:justify-center md:p-8' => $featured])>
        @if ($post->category)
            <p class="text-xs font-semibold uppercase tracking-wider text-brand-700">{{ $post->category->name }}</p>
        @endif

        <h2 @class([
            'mt-2 font-semibold tracking-tight text-slate-950',
            'text-2xl sm:text-3xl' => $featured,
            'text-xl' => ! $featured,
        ])>
            <a href="{{ route('posts.show', $post->slug) }}" class="after:absolute after:inset-0 focus-visible:outline-none">
                {{ $post->title }}
            </a>
        </h2>

        <p @class(['mt-3 text-slate-600', 'text-lg' => $featured])>{{ $post->excerpt }}</p>

        <x-blog.post-meta :post="$post" class="mt-auto pt-5" />
    </div>
</article>
