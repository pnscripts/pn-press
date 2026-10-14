@extends('layouts.app')

@section('title', $page->metaTitle())
@section('description', (string) $page->metaDescription())
@if ($page->noindex || ! $page->isPublic())
    @section('robots', 'noindex, nofollow')
@endif

@section('content')
    <article class="press-container max-w-3xl pt-10 sm:pt-16">
        <h1 class="font-display text-4xl font-semibold leading-tight tracking-tight sm:text-6xl">{{ $page->title }}</h1>
        @if ($page->excerpt)
            <p class="mt-4 font-display text-xl italic text-muted">{{ $page->excerpt }}</p>
        @endif
        <div class="mt-6 border-b border-rule"></div>
        <div class="press-blocks [&_.press-container]:px-0">
            {{ app(\App\Press\Blocks\BlockRegistry::class)->render(apply_filters('page.blocks', $page->blocks ?? [], $page), $page) }}
        </div>
    </article>
    @hook('page.after_content', $page)
@endsection
