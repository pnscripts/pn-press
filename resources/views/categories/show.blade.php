@extends('layouts.app')

@section('title', $category->name)
@section('description', $category->description ?: 'Posts in '.$category->name.'.')

@section('content')
    <section class="bg-navy-950 pb-10 text-white">
        <div class="mx-auto max-w-6xl px-4 pt-8 sm:px-6 sm:pt-12">
            <p class="text-sm font-semibold uppercase tracking-wider text-brand-300">Category</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight sm:text-5xl">{{ $category->name }}</h1>
            @if ($category->description)
                <p class="mt-3 max-w-2xl text-lg text-slate-300">{{ $category->description }}</p>
            @endif
            <x-blog.category-nav :categories="$categories" :active="$category->slug" class="mt-8" />
        </div>
    </section>

    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 sm:py-14">
        @if ($posts->isEmpty())
            <p class="rounded-2xl bg-white p-8 text-slate-600 ring-1 ring-slate-200">No published posts in this category yet.</p>
        @else
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
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
