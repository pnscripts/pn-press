@php($posts = \App\Models\Post::query()->published()->with('category')->latest('published_at')->limit(max(1, min(12, (int) ($data['count'] ?? 3))))->get())
@if ($posts->isNotEmpty())
    <section class="press-container py-12">
        <div class="flex items-end justify-between border-b-2 border-ink pb-3">
            <h2 class="font-display text-3xl font-semibold tracking-tight">{{ $data['heading'] ?? 'From the blog' }}</h2>
            <a href="{{ route('blog') }}" class="text-sm font-medium text-accent hover:underline">All posts →</a>
        </div>
        <div class="divide-y divide-rule">
            @foreach ($posts as $post)
                <x-blog.post-card :post="$post" />
            @endforeach
        </div>
    </section>
@endif
