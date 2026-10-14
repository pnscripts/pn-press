<section class="press-container py-12">
    @if (!empty($data['heading']))
        <h2 class="max-w-2xl font-display text-3xl font-semibold tracking-tight sm:text-4xl">{{ $data['heading'] }}</h2>
    @endif
    <div class="mt-8 grid gap-px overflow-hidden rounded-sm border border-rule bg-rule sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($data['items'] ?? [] as $i => $item)
            <div class="bg-surface p-6">
                <p class="font-display text-sm font-semibold text-accent">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</p>
                <h3 class="mt-2 text-lg font-semibold">{{ $item['title'] ?? '' }}</h3>
                @if (!empty($item['text']))
                    <p class="mt-2 text-muted">{{ $item['text'] }}</p>
                @endif
            </div>
        @endforeach
    </div>
</section>
