@props(['post', 'featured' => false])

@if ($featured)
    <article class="group relative grid gap-6 md:grid-cols-[3fr_2fr] md:items-center md:gap-10">
        @if ($post->featuredImageUrl())
            <div class="overflow-hidden rounded-sm bg-rule">
                <img src="{{ $post->featuredImageUrl() }}" alt="" loading="lazy" class="aspect-[1200/630] w-full object-cover transition duration-500 group-hover:scale-[1.02]">
            </div>
        @endif
        <div>
            @if ($post->category)
                <p class="press-eyebrow">{{ $post->category->name }}</p>
            @endif
            <h2 class="mt-3 font-display text-3xl font-semibold leading-tight tracking-tight sm:text-4xl">
                <a href="{{ route('posts.show', $post->slug) }}" class="after:absolute after:inset-0 group-hover:underline group-hover:decoration-highlight group-hover:decoration-4 group-hover:underline-offset-4">{{ $post->title }}</a>
            </h2>
            <p class="mt-4 text-lg text-muted">{{ $post->excerpt }}</p>
            <x-blog.post-meta :post="$post" class="mt-5" />
        </div>
    </article>
@else
    <article class="group relative grid gap-4 py-7 sm:grid-cols-[9rem_1fr_10rem] sm:gap-8">
        <x-blog.post-meta :post="$post" class="sm:flex-col sm:items-start sm:gap-1 sm:pt-1" />
        <div>
            @if ($post->category)
                <p class="press-eyebrow">{{ $post->category->name }}</p>
            @endif
            <h2 class="mt-2 font-display text-2xl font-semibold leading-snug tracking-tight">
                <a href="{{ route('posts.show', $post->slug) }}" class="after:absolute after:inset-0 group-hover:underline group-hover:decoration-highlight group-hover:decoration-4 group-hover:underline-offset-4">{{ $post->title }}</a>
            </h2>
            <p class="mt-2 text-muted">{{ $post->excerpt }}</p>
        </div>
        @if ($post->featuredImageUrl())
            <img src="{{ $post->featuredImageUrl() }}" alt="" loading="lazy" class="hidden aspect-[4/3] w-full rounded-sm object-cover sm:block">
        @endif
    </article>
@endif
