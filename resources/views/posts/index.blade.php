@extends('layouts.app')

@section('title', request()->routeIs('home', '*.home') ? app(\App\Press\Settings::class)->siteName() : __('Journal'))

@section('content')
    <section class="press-container pt-10 sm:pt-16">
        <p class="press-eyebrow">{{ __('The journal') }}</p>
        <div class="mt-3 flex flex-col gap-6 border-b-2 border-ink pb-6 sm:flex-row sm:items-end sm:justify-between">
            <h1 class="font-display text-5xl font-semibold tracking-tight sm:text-7xl">{{ __('Latest writing') }}</h1>
            <x-blog.category-nav :categories="$categories" />
        </div>
    </section>

    <div class="press-container py-10">
        @if ($posts->isEmpty())
            <p class="rounded-lg border border-dashed border-rule bg-surface p-8 text-muted">{{ __('No published posts yet.') }}</p>
        @else
            @php($showFeatured = $posts->onFirstPage())
            @if ($showFeatured)
                <x-blog.post-card :post="$posts->first()" featured />
            @endif

            <div @class(['divide-y divide-rule', 'mt-10 border-t border-rule' => $showFeatured])>
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
