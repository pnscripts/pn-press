@extends('layouts.app')

@section('title', $page->metaTitle())
@section('description', (string) $page->metaDescription())
@if ($page->noindex || ! $page->isPublic())
    @section('robots', 'noindex, nofollow')
@endif
@if ($og = \App\Models\MenuItem::safeUrl($page->og_image))
    @section('og_image', str_starts_with($og, '/') ? url($og) : $og)
@endif

@section('content')
    @unless ($page->isPublic())
        <p class="bg-highlight px-4 py-2 text-center text-sm font-medium text-[#1b1a17]">{{ __('Preview: this page is not published.') }}</p>
    @endunless

    @if ($page->template !== 'landing')
        <header class="press-container pt-10 sm:pt-16">
            @if ($page->parent)
                <a href="{{ $page->parent->url() }}" class="press-eyebrow hover:underline">{{ $page->parent->title }}</a>
            @endif
            <h1 class="mt-3 max-w-4xl font-display text-5xl font-semibold leading-[1.05] tracking-tight sm:text-7xl">{{ $page->title }}</h1>
            @if ($page->excerpt)
                <p class="mt-5 max-w-2xl font-display text-xl italic text-muted sm:text-2xl">{{ $page->excerpt }}</p>
            @endif
            <div class="mt-8 border-b-2 border-ink"></div>
        </header>
    @endif

    <div class="press-blocks">
        {{ app(\App\Press\Blocks\BlockRegistry::class)->render(apply_filters('page.blocks', $page->blocks ?? [], $page), $page) }}
    </div>

    @hook('page.after_content', $page)
@endsection
