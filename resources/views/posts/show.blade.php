@extends('layouts.app')

@section('title', $post->meta_title ?: $post->title)
@section('description', $post->meta_description ?: $post->excerpt)
@if ($post->featuredImageUrl())
    @section('og_image', $post->featuredImageUrl())
@endif

@section('content')
    <article>
        <header class="press-container max-w-3xl pt-10 sm:pt-16">
            <a href="{{ press_route('blog') }}" class="text-sm font-medium text-muted hover:text-ink">&larr; {{ __('Journal') }}</a>
            @if ($post->category)
                <p class="mt-8">
                    <a href="{{ $post->category->url() }}" class="press-eyebrow hover:underline">{{ $post->category->name }}</a>
                </p>
            @endif
            <h1 class="mt-3 font-display text-4xl font-semibold leading-[1.1] tracking-tight sm:text-6xl">{{ $post->title }}</h1>
            <p class="mt-5 font-display text-xl italic text-muted sm:text-2xl">{{ $post->excerpt }}</p>
            <p class="mt-6 flex flex-wrap items-center gap-x-3 gap-y-1 border-y border-rule py-3 text-sm text-muted">
                @if ($post->author)
                    <span class="font-semibold text-ink">{{ $post->author->name }}</span>
                @endif
                <time datetime="{{ $post->published_at?->toDateString() }}">{{ $post->published_at?->translatedFormat('j F Y') }}</time>
                <span>{{ __(':count min read', ['count' => $post->readingTimeMinutes()]) }}</span>
            </p>
        </header>

        @if ($post->featuredImageUrl())
            <div class="press-container mt-8 max-w-5xl">
                <img src="{{ $post->featuredImageUrl() }}" alt="" class="aspect-[1200/630] w-full rounded-sm object-cover">
            </div>
        @endif

        <div class="press-container max-w-3xl">
            <div class="press-prose py-10 sm:py-14">
                {{-- Sanitised: an editor's HTML must not run scripts for visitors or admins. Filters may add trusted markup. --}}
                {!! apply_filters('post.content', \Illuminate\Support\Str::sanitizeHtml((string) $post->body), $post) !!}
            </div>
            @hook('post.after_content', $post)
        </div>
    </article>

    @if ($related->isNotEmpty())
        <section aria-labelledby="related-heading" class="press-container border-t-2 border-ink pt-8">
            <h2 id="related-heading" class="font-display text-3xl font-semibold tracking-tight">
                {{ $post->category ? __('More in :name', ['name' => $post->category->name]) : __('More writing') }}
            </h2>
            <div class="mt-4 divide-y divide-rule">
                @foreach ($related as $item)
                    <x-blog.post-card :post="$item" />
                @endforeach
            </div>
        </section>
    @endif
@endsection
