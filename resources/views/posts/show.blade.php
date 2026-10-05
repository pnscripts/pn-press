@extends('layouts.app')

@section('title', $post->title)
@section('description', $post->excerpt)
@if ($post->featuredImageUrl())
    @section('og_image', $post->featuredImageUrl())
@endif

@section('content')
    <article>
        <header class="bg-navy-950 text-white">
            <div class="mx-auto max-w-3xl px-4 pb-24 pt-8 sm:px-6 sm:pt-12">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-1 text-sm font-medium text-slate-300 hover:text-white">
                    <span aria-hidden="true">&larr;</span> All posts
                </a>
                @if ($post->category)
                    <p class="mt-6">
                        <a href="{{ route('categories.show', $post->category->slug) }}" class="text-sm font-semibold uppercase tracking-wider text-brand-300 hover:underline">
                            {{ $post->category->name }}
                        </a>
                    </p>
                @endif
                <h1 class="mt-3 text-3xl font-semibold leading-tight tracking-tight sm:text-5xl">{{ $post->title }}</h1>
                <p class="mt-4 text-lg text-slate-300 sm:text-xl">{{ $post->excerpt }}</p>
                <p class="mt-6 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-slate-300">
                    @if ($post->author)
                        <span class="font-medium text-white">{{ $post->author->name }}</span>
                        <span aria-hidden="true">·</span>
                    @endif
                    <time datetime="{{ $post->published_at?->toDateString() }}">{{ $post->published_at?->format('M j, Y') }}</time>
                    <span aria-hidden="true">·</span>
                    <span>{{ $post->readingTimeMinutes() }} min read</span>
                </p>
            </div>
        </header>

        <div class="mx-auto max-w-3xl px-4 sm:px-6">
            @if ($post->featuredImageUrl())
                <img src="{{ $post->featuredImageUrl() }}" alt="" class="-mt-16 aspect-[1200/630] w-full rounded-2xl object-cover shadow-lg ring-1 ring-slate-900/10">
            @else
                <div class="-mt-16"></div>
            @endif

            <div class="post-body py-10 sm:py-14">
                {!! $post->body !!}
            </div>
        </div>
    </article>

    @if ($related->isNotEmpty())
        <section aria-labelledby="related-heading" class="border-t border-slate-200 bg-white">
            <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
                <h2 id="related-heading" class="text-2xl font-semibold tracking-tight">
                    More {{ $post->category ? 'in '.$post->category->name : 'posts' }}
                </h2>
                <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($related as $item)
                        <x-blog.post-card :post="$item" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif
@endsection
