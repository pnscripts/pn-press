@extends('layouts.app')

@section('title', config('app.name'))

@section('content')
    <section class="bg-navy-950 pb-10 text-white">
        <div class="mx-auto max-w-6xl px-4 pt-8 sm:px-6 sm:pt-12">
            <p class="text-sm font-semibold uppercase tracking-wider text-brand-300">The blog</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight sm:text-5xl">Latest posts</h1>
            <p class="mt-3 max-w-2xl text-lg text-slate-300">Articles, guides and notes, newest first.</p>
            <x-blog.category-nav :categories="$categories" class="mt-8" />
        </div>
    </section>

    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 sm:py-14">
        @if ($posts->isEmpty())
            <p class="rounded-2xl bg-white p-8 text-slate-600 ring-1 ring-slate-200">No published posts yet.</p>
        @else
            @php($showFeatured = $posts->onFirstPage())
            @if ($showFeatured)
                <x-blog.post-card :post="$posts->first()" featured />
            @endif

            <div @class(['grid gap-6 sm:grid-cols-2 lg:grid-cols-3', 'mt-8' => $showFeatured])>
                @foreach ($showFeatured ? $posts->slice(1) : $posts as $post)
                    <x-blog.post-card :post="$post" />
                @endforeach
            </div>
        @endif

        <div class="mt-12">
            {{ $posts->links() }}
        </div>
    </div>
@endsection
