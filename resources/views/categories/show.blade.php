@extends('layouts.app')

@section('title', $category->name)
@section('description', $category->description ?: 'Posts in '.$category->name.'.')

@section('content')
    <section class="press-container pt-10 sm:pt-16">
        <p class="press-eyebrow">Category</p>
        <div class="mt-3 flex flex-col gap-6 border-b-2 border-ink pb-6 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="font-display text-5xl font-semibold tracking-tight sm:text-6xl">{{ $category->name }}</h1>
                @if ($category->description)
                    <p class="mt-3 max-w-2xl text-lg text-muted">{{ $category->description }}</p>
                @endif
            </div>
            <x-blog.category-nav :categories="$categories" :active="$category->slug" />
        </div>
    </section>

    <div class="press-container py-10">
        @if ($posts->isEmpty())
            <p class="rounded-lg border border-dashed border-rule bg-surface p-8 text-muted">No published posts in this category yet.</p>
        @else
            <div class="divide-y divide-rule">
                @foreach ($posts as $post)
                    <x-blog.post-card :post="$post" />
                @endforeach
            </div>
        @endif

        <div class="mt-12">
            {{ $posts->links() }}
        </div>
    </div>
@endsection
